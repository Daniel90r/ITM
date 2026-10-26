// Navegador y servidor exclusivos: solo HTML sintético, sin Laravel ni MySQL en ejecución.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http'),{spawn}=require('node:child_process');
const dir=process.env.ITM_ALUMNOS_FILTROS_PREVIEW_DIR;if(!dir)throw Error('Define ITM_ALUMNOS_FILTROS_PREVIEW_DIR.');
const ids=JSON.parse(fs.readFileSync(path.join(dir,'filtros.json'),'utf8'));
const manifest=JSON.parse(fs.readFileSync('public/build/manifest.json','utf8'));
const css=fs.readFileSync('public/build/'+manifest['resources/css/app.css'].file,'utf8');
const bundle=fs.readFileSync('public/build/'+manifest['resources/js/admin/alumnos.js'].file,'utf8');
const logo='data:image/jpeg;base64,'+fs.readFileSync('public/images/logo_itm.jpg').toString('base64');
const pause=ms=>new Promise(r=>setTimeout(r,ms)),requests=[];let origin;
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,origin);if(url.pathname!=='/admin/alumnos'){res.setHeader('Content-Type','application/json');res.end('{"alumnos":[]}');return;}
 requests.push({url:url.href,ajax:req.headers['x-requested-with']==='XMLHttpRequest'});
 if(url.searchParams.get('q')==='ErrorSimulado'){res.writeHead(500);res.end('Error de prueba');return;}
 const p=url.searchParams;const file=p.get('q')==='SinCoincidencias'?'vacio':p.get('q')==='Alfonso'?'busqueda':p.get('curso_id')===String(ids.historico)?'historico-curso':p.get('curso_id')===String(ids.activo)?'curso':p.get('anio')==='2025'?'anio':p.get('incluir_historicos')==='1'?'historicos':'activos';
 const html=fs.readFileSync(path.join(dir,file+'.html'),'utf8').replaceAll('http://localhost/images/logo_itm.jpg',logo).replaceAll('http://localhost',origin).replace('</head>','<style>'+css+'</style></head>').replace('</body>','<script type="module">'+bundle+'</script></body>');
 res.setHeader('Content-Type','text/html;charset=utf-8');res.end(html);
});
(async()=>{let ws,browser;try{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'itm-alumnos-ajax-'));
 browser=spawn(process.env.ITM_CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--remote-debugging-port=0','--user-data-dir='+profile,'--no-first-run','--disable-background-networking','about:blank'],{windowsHide:true,stdio:'ignore'});
 let port;for(let i=0;i<80;i++){try{port=+fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];break}catch{await pause(100)}}
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let id=0;const pending=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result)}};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}))});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
 const until=async expr=>{for(let i=0;i<100;i++){if(await evaluate(expr))return;await pause(40)}throw Error('Espera agotada: '+expr)};
 const change=async(id,value)=>{await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(String(value))};e.dispatchEvent(new Event('change',{bubbles:true}))})()`)};
 const rows=`document.querySelectorAll('.fila-alumno').length`;
 const stats=async expected=>{const actual=await evaluate(`[...document.querySelectorAll('[data-alumnos-resumen] > div > div:last-child > p:first-child')].map(p=>Number(p.textContent))`);if(JSON.stringify(actual)!==JSON.stringify(expected))throw Error('Estadísticas incorrectas: '+JSON.stringify(actual))};
 const clear=async()=>{await evaluate(`document.querySelector('.alumnos-limpiar').click()`);await until(`${rows}===3 && !location.search && document.querySelector('[data-alumnos-resultados]').getAttribute('aria-busy')==='false'`)};
 await send('Page.enable');await send('Network.enable');await send('Network.setBlockedURLs',{urls:['http://localhost/*','http://itm.test/*','https://*']});
 for(const width of [320,375,390,768,1440]){
  const start=requests.length;await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await send('Page.navigate',{url:origin+'/admin/alumnos'});
  await until(`document.getElementById('alumnos-alcance')?.dataset.consultaDinamica==='true' && document.body.hasAttribute('data-staff-ready')`);
  await evaluate(`window.sidebarOriginal=document.querySelector('[data-staff-sidebar]');window.headerOriginal=document.querySelector('.alumnos-panel>header')`);
  await change('alumnos-curso-consulta',ids.activo);await until(`${rows}===1 && location.search.includes('curso_id=${ids.activo}')`);
  await stats([1,1,0]);await clear();await change('filtrarAnio',2025);await until(`${rows}===2 && location.search.includes('anio=2025')`);await stats([2,1,1]);await clear();
  const beforeSearch=requests.length;
  await evaluate(`(()=>{const q=document.getElementById('buscarAlumno');q.focus();for(const value of ['Al','Alf','Alfonso']){q.value=value;q.dispatchEvent(new Event('input',{bubbles:true}))}})()`);
  await until(`${rows}===1 && location.search.includes('q=Alfonso')`);
  const searchRequests=requests.slice(beforeSearch).filter(r=>r.url.includes('/admin/alumnos?'));
  if(searchRequests.length!==1||!await evaluate(`document.activeElement.id==='buscarAlumno'`))throw Error('Búsqueda duplicada o pérdida de foco');
  await clear();await evaluate(`document.querySelector('#alumnos-alcance input[type=checkbox]').click()`);await until(`${rows}===4 && location.search.includes('incluir_historicos=1')`);
  await stats([4,3,1]);await change('alumnos-curso-consulta',ids.historico);await until(`${rows}===1 && document.querySelector('.fila-alumno').dataset.nombre.includes('carla')`);
  await evaluate(`document.querySelector('.fila-alumno button').click()`);if(!await evaluate(`document.getElementById('modalEditarAlumno').style.display==='flex' && document.getElementById('editNombre').value==='Carla'`))throw Error('Editar no funciona tras AJAX');
  await evaluate(`cerrarModalEditar()`);
  if(!await evaluate(`document.querySelector('.fila-alumno a').getAttribute('href').includes('/personas/alumnos/')`))throw Error('Enlace de estado perdido');
  await clear();await evaluate(`history.back()`);await until(`document.getElementById('alumnos-curso-consulta').value==='${ids.historico}' && document.querySelector('#alumnos-alcance input[type=checkbox]').checked`);
  await evaluate(`history.forward()`);await until(`${rows}===3 && !location.search && document.getElementById('alumnos-curso-consulta').value===''`);
  await evaluate(`(()=>{const q=document.getElementById('buscarAlumno');q.value='ErrorSimulado';q.dispatchEvent(new Event('input',{bubbles:true}))})()`);await until(`document.querySelector('[data-alumnos-error]').textContent.length>0`);
  if(!await evaluate(`${rows}===3 && !location.search`))throw Error('Un fallo eliminó los resultados');
  await evaluate(`(()=>{const q=document.getElementById('buscarAlumno');q.value='';q.dispatchEvent(new Event('input',{bubbles:true}))})()`);await until(`!document.querySelector('[data-alumnos-error]').textContent && document.querySelector('[data-alumnos-carga]').textContent==='Resultados actualizados.'`);
  if(!await evaluate(`sidebarOriginal===document.querySelector('[data-staff-sidebar]') && headerOriginal===document.querySelector('.alumnos-panel>header') && document.documentElement.scrollWidth<=innerWidth`))throw Error('Layout reemplazado o desbordado');
  if(requests.slice(start).filter(r=>!r.ajax).length!==1)throw Error('Navegación completa inesperada');
  const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'ajax-'+width+'.png'),Buffer.from(shot.data,'base64'));
  console.log(JSON.stringify({width,passed:true,documentNavigations:1}));
 }
}finally{ws?.close();browser?.kill();server.close()}})().catch(e=>{console.error(e);process.exitCode=1});