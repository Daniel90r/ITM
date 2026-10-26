// Solo HTML sintético exportado por BoletasPresentacionTest en almacenamiento temporal.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
const dir=process.env.ITM_BOLETAS_PREVIEW_DIR;
if(!dir) throw Error('Define ITM_BOLETAS_PREVIEW_DIR con las vistas sintéticas.');
const pause=ms=>new Promise(r=>setTimeout(r,ms));
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'itm-boletas-chrome-'));
const browser=spawn(process.env.ITM_CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--remote-debugging-port=0','--user-data-dir='+profile,'--no-first-run','--disable-background-networking','about:blank'],{windowsHide:true,stdio:'ignore'});
(async()=>{let ws;try{
    let port;for(let i=0;i<80;i++){try{port=+fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];break}catch{await pause(100)}}
    const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
    ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
    let id=0;const pending=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result)}};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}))});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
    await send('Page.enable');await send('Network.enable');await send('Network.setBlockedURLs',{urls:['http://*','https://*']});
    const manifest=JSON.parse(fs.readFileSync('public/build/manifest.json','utf8'));
    const css=fs.readFileSync('public/build/'+manifest['resources/css/app.css'].file,'utf8');
    const logo='data:image/jpeg;base64,'+fs.readFileSync('public/images/logo_itm.jpg').toString('base64');
    const checks=[];
    for(const file of ['uno','varios','historico','vacio'])for(const width of [320,375,390,768,1440]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        const html=fs.readFileSync(path.join(dir,file+'.html'),'utf8').replace('</head>','<style>'+css+'</style></head>').replaceAll('http://localhost/images/logo_itm.jpg',logo);
        await send('Page.setDocumentContent',{frameId:(await send('Page.getFrameTree')).frameTree.frame.id,html});await pause(70);
        await evaluate(fs.readFileSync('resources/js/staff/responsive.js','utf8'));await pause(70);
        const layout=await evaluate(`(()=>{
            const form=document.getElementById('boletas-filtros-form'),select=document.getElementById('boletas-curso'),search=document.getElementById('buscadorCurso'),cards=[...document.querySelectorAll('.card-curso')];
            const sr=select.getBoundingClientRect(),ir=search.getBoundingClientRect();
            return {overflow:document.documentElement.scrollWidth>innerWidth,sameRow:Math.abs(sr.top-ir.top)<2,count:cards.length,maxCard:Math.max(0,...cards.map(c=>c.getBoundingClientRect().width)),fieldsInside:form.contains(search)&&form.contains(select),gap:cards[0]?cards[0].getBoundingClientRect().top-document.querySelector('.boletas-filtros').getBoundingClientRect().bottom:0};
        })()`);
        if(layout.overflow||layout.maxCard>401||!layout.fieldsInside||(width>=768&&!layout.sameRow)||(width<=390&&layout.sameRow)||layout.gap>20)throw Error(JSON.stringify({file,width,layout}));
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,file+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
        const behavior=await evaluate(`(()=>{
            const search=document.getElementById('buscadorCurso'),form=search.form;let submits=0;form.addEventListener('submit',e=>{e.preventDefault();submits++});
            search.value='SINCOINCIDENCIAS';filtrarCursos();
            const empty=[...document.querySelectorAll('.card-curso')].every(c=>c.style.display==='none');
            const enter=!search.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));search.value='';filtrarCursos();
            const btn=document.querySelector('.boleta-curso-accion');let navigation=true;
            if(btn){btn.click();navigation=document.getElementById('vistaAlumnos').style.display==='flex'&&document.querySelector('.boleta-alumno-enlace').getAttribute('href').includes('/boletas/');volverACursos();navigation=navigation&&document.getElementById('vistaCursos').style.display==='flex'&&document.querySelector('.card-curso').style.display==='flex'}
            const checkbox=form.querySelector('input[type=checkbox]');form.requestSubmit=()=>{submits++};checkbox.click();
            return {empty,enter,navigation,oneSubmit:submits===1,overflow:document.documentElement.scrollWidth>innerWidth};
        })()`);
        if(!behavior.empty||!behavior.enter||!behavior.navigation||!behavior.oneSubmit||behavior.overflow)throw Error(JSON.stringify({file,width,behavior}));
        checks.push({file,width});
    }
    fs.writeFileSync(path.join(dir,'resultados.json'),JSON.stringify({checks,failures:0},null,2));
    console.log(JSON.stringify({checks:checks.length,failures:0}));
}finally{ws?.close();browser.kill()}})().catch(e=>{console.error(e);process.exitCode=1});
