// Fixtures de ReporteVisorTest; HTTP local limitado a datos sintéticos y assets explícitos.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process'),{createHash}=require('node:crypto');
const dir=path.resolve('storage/app/reportes-referencia/visor');
const manifest=JSON.parse(fs.readFileSync(path.join(dir,'manifest.json'),'utf8'));
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const assets=new Map([
 ['/css/reportes-visor.css',['public/css/reportes-visor.css','text/css']],
 ['/js/reportes-visor.js',['public/js/reportes-visor.js','text/javascript']],
 ['/images/logo_itm.jpg',['public/images/logo_itm.jpg','image/jpeg']],
 ['/favicon.ico',['public/favicon.ico','image/x-icon']],
 ...[32,180,192].map(n=>['/favicons/itm-'+n+'.png',['public/favicons/itm-'+n+'.png','image/png']]),
]);
let base;const requests=new Map();
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,base),asset=assets.get(u.pathname);
 if(asset){res.writeHead(200,{'Content-Type':asset[1]});return res.end(fs.readFileSync(asset[0]));}
 for(const [type,entry] of Object.entries(manifest)){
  const target=new URL(entry.url);
  if(u.pathname===target.pathname&&u.search===target.search){requests.set(type,(requests.get(type)||0)+1);res.writeHead(200,{'Content-Type':'application/pdf','Content-Disposition':entry.disposition,'Cache-Control':'private, no-store'});return res.end(fs.readFileSync(path.join(dir,type+'.pdf')));}
 }
 const error=/^\/error-(403|422|302|html)\.pdf$/.exec(u.pathname);
 if(error){if(error[1]==='302'){res.writeHead(302,{Location:'/login'});return res.end();}res.writeHead(error[1]==='html'?200:Number(error[1]),{'Content-Type':'text/html'});return res.end('Respuesta de prueba; no es PDF');}
 const page=/^\/(alumnos|cursos|asistencias|mensualidades|maestros|inscripciones|error-403|error-422|error-302|error-html)\.html$/.exec(u.pathname);
 if(page){
  const type=page[1].startsWith('error-')?'alumnos':page[1];
  let html=fs.readFileSync(path.join(dir,type+'.html'),'utf8').replaceAll('http://localhost',base);
  if(page[1].startsWith('error-'))html=html.replace(/data-pdf-url="[^"]+"/,'data-pdf-url="'+base+'/'+page[1]+'.pdf"');
  res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Content-Security-Policy':manifest[type].csp});return res.end(html);
 }
 res.writeHead(404);res.end();
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));base='http://127.0.0.1:'+server.address().port;
 const results=[];
 try{
  for(const [name,exe] of [['Chrome','C:/Program Files/Google/Chrome/Application/chrome.exe'],['Edge','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe']]){
   const profile=fs.mkdtempSync(path.join(dir,name+'-'));
   const browser=spawn(exe,['--headless=new','--remote-debugging-port=0','--user-data-dir='+profile,'--no-first-run','--no-default-browser-check','--disable-background-networking','about:blank'],{windowsHide:true,stdio:'ignore'});
   let startupError;browser.on('error',e=>{startupError=e;});
   let info;
   for(let i=0;i<200;i++){if(startupError)throw startupError;try{const port=Number(fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0]);info=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();break;}catch{await pause(100);}}
   if(!info){browser.kill();throw Error(name+' no inicia');}
   const ws=new WebSocket(info.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
   let id=0;const pending=new Map();ws.addEventListener('message',e=>{const m=JSON.parse(e.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(m.error):p.resolve(m.result);}});
   const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
   const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
   try{
    const version=(await send('Browser.getVersion')).product;await send('Page.enable');
    for(const width of [320,375,390,768,1440]){
     await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});
     for(const type of Object.keys(manifest)){
      const before=requests.get(type)||0,url=base+'/'+type+'.html';await send('Page.navigate',{url});
      let state;
      for(let i=0;i<200;i++){
       await pause(40);
       state=await evaluate(`(()=>{if(location.href!==${JSON.stringify(url)} || !document.getElementById('visor-reporte'))return null;const f=document.getElementById('documento-pdf'),d=document.getElementById('descargar-pdf');return {title:document.title,width:innerWidth,scroll:document.documentElement.scrollWidth,frame:f?.src,hidden:f?.hidden,download:d?.href,filename:d?.download,print:document.getElementById('imprimir-pdf')?.disabled,status:document.getElementById('visor-status')?.textContent}})()`);
       if(state?.frame?.startsWith('blob:')&&!state.hidden&&!state.print)break;
      }
      assert.ok(state?.frame?.startsWith('blob:')&&!state.hidden&&!state.print,JSON.stringify({name,type,width,state}));
      assert.ok(state.scroll<=width,'sin desbordamiento');assert.equal(state.frame,state.download,'misma copia');assert.ok(state.filename.endsWith('.pdf'));
      assert.equal((requests.get(type)||0)-before,1,'una generación por apertura');
      const downloadDir=fs.mkdtempSync(path.join(dir,'download-'));
      await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:downloadDir});
      await evaluate(`document.getElementById('descargar-pdf').click()`);
      let downloaded;
      for(let i=0;i<100;i++){downloaded=fs.readdirSync(downloadDir).find(f=>f.endsWith('.pdf'));if(downloaded)break;await pause(50);}
      assert.ok(downloaded,'descarga del documento');
      const hash=createHash('sha256').update(fs.readFileSync(path.join(downloadDir,downloaded))).digest('hex');
      assert.equal(hash,createHash('sha256').update(fs.readFileSync(path.join(dir,type+'.pdf'))).digest('hex'),'bytes intactos');
      assert.equal((requests.get(type)||0)-before,1,'descargar no regenera el PDF');
      const icon=await evaluate(`(async()=>{const i=new Image;i.src=document.querySelector('link[rel="icon"][sizes="32x32"]').href;await i.decode();return {width:i.naturalWidth,height:i.naturalHeight};})()`);assert.deepEqual(icon,{width:32,height:32});
      if(width===320||width===1440){await pause(1500);const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,name+'-'+type+'-'+width+'.png'),Buffer.from(shot.data,'base64'));}
      // Comprobar la acción del botón sin abrir un diálogo de impresión físico.
      const printed=await evaluate(`(()=>{let calls=0;const w=document.getElementById('documento-pdf').contentWindow;try{w.print=()=>{calls++};document.getElementById('imprimir-pdf').click();return calls===1||document.getElementById('visor-status').textContent.includes('barra del documento');}catch{return true;}})()`);assert.ok(printed);
      results.push({name,version,type,width,title:state.title,hash});
     }
    }
    for(const code of ['403','422','302','html']){
     const url=base+'/error-'+code+'.html';await send('Page.navigate',{url});let state;
     for(let i=0;i<100;i++){await pause(40);state=await evaluate(`location.href===${JSON.stringify(url)}?({hidden:document.getElementById('documento-pdf')?.hidden,status:document.getElementById('visor-status')?.textContent}):null`);if(state?.status&&!state.status.startsWith('Cargando'))break;}
     assert.ok(state?.hidden&&state.status&&!state.status.startsWith('Cargando'),JSON.stringify(state));
    }
   }finally{await send('Browser.close').catch(()=>{});ws.close();}
  }
  fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks:results.length,errorChecks:8,failures:0,results},null,2));
  console.log(JSON.stringify({checks:results.length,errorChecks:8,failures:0,browsers:[...new Set(results.map(r=>r.name+' '+r.version))]}));
 }finally{await new Promise(r=>server.close(r));}
})().catch(error=>{console.error(error);process.exitCode=1;});
