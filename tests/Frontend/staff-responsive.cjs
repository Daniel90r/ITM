const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {pathToFileURL} = require('node:url');
const assert = require('node:assert/strict');
const dir = path.resolve('storage/app/reportes-referencia/responsive');
const profile = fs.mkdtempSync(path.join(dir, 'chrome-test-'));
let port;
const browser = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--remote-debugging-port=0', '--user-data-dir='+profile, '--no-first-run','--no-default-browser-check','--disable-background-networking','--allow-file-access-from-files','about:blank'], {windowsHide:true,stdio:'ignore'});
browser.on('error', e=>{ console.error(e); process.exitCode=1; });
const pause=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 let info;
 for(let i=0;i<80;i++){try { port=Number(fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0]); info=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();break;}catch{await pause(250);}}
 assert.ok(info,'Browser startup');
 const ws=new WebSocket(info.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let id=0; const pending=new Map();
 ws.addEventListener('message',event=>{const d=JSON.parse(event.data);if(pending.has(d.id)){const {resolve,reject}=pending.get(d.id);pending.delete(d.id); d.error?reject(d.error):resolve(d.result);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});
 const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
 const failures=[],results=[];
 try {
 await send('Page.enable');
 // No requests to the application: fixtures are rendered against SQLite and all HTTP is blocked.
 await send('Network.enable');await send('Network.setBlockedURLs',{urls:['http://*','https://*']});
 for(const file of fs.readdirSync(dir).filter(f=>f.endsWith('.html')&&(!process.argv[2]||f===process.argv[2]))){
  for(const width of [320,375,390,768,1440]){
   await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
   const targetURL=pathToFileURL(path.join(dir,file)).href;
   await send('Page.navigate',{url:targetURL});
   for(let attempt=0;attempt<60;attempt++){
    await pause(50);
    if(await evaluate(`location.href===${JSON.stringify(targetURL)} && document.body?.hasAttribute('data-staff-ready')`))break;
   }
   await pause(80);
   const state=await evaluate(`(()=>{const p=document.querySelector('[data-staff-panel]');return {ready:document.body.hasAttribute('data-staff-ready'),width:innerWidth,scroll:document.documentElement.scrollWidth,panel:!!p,sidebar:getComputedStyle(document.querySelector('[data-staff-sidebar]')).visibility,toggle:getComputedStyle(document.querySelector('.staff-mobile-bar')).display,tables:[...document.querySelectorAll('table')].every(t=>!!t.closest('.staff-table-scroll'))}})()`);
   const errors=[];
   if(!state?.ready||!state.panel)errors.push('shell not initialized');
   if(state.scroll>width+1)errors.push('page overflow '+state.scroll);
   if(!state.tables)errors.push('table not contained');
   const clipped=await evaluate(`(()=>[...document.querySelectorAll('[data-staff-panel] input,[data-staff-panel] select,[data-staff-panel] button,[data-staff-panel] a')].filter(e=>!e.closest('.staff-table-scroll')&&e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden').map(e=>({text:e.textContent.trim().slice(0,40)||e.name,left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right})).filter(e=>e.left< -1||e.right>innerWidth+1))()`);
   if(clipped.length)errors.push('clipped controls '+JSON.stringify(clipped.slice(0,6)));
   if(width<1024){
    const modals=await evaluate(`(()=>{const failures=[];for(const m of document.querySelectorAll('[data-staff-modal]')){const old=m.getAttribute('style');const hidden=m.classList.contains('hidden');m.classList.remove('hidden');m.style.display='flex';const box=m.firstElementChild?.getBoundingClientRect();if(box&&(box.left< -1||box.right>innerWidth+1))failures.push(m.id);if(old===null)m.removeAttribute('style');else m.setAttribute('style',old);if(hidden)m.classList.add('hidden');}return failures})()`);
    if(modals.length)errors.push('modal overflow '+modals.join(','));
   }
   if(width<1024){
    if(file==='admin-alumnos.html'&&width===320){
      await evaluate(`document.getElementById('staff-menu-toggle').click()`);
      let shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'menu-320.png'),Buffer.from(shot.data,'base64'));
      await evaluate(`document.querySelector('.staff-menu-close').click();document.getElementById('modalNuevoAlumno').style.display='flex'`);
      shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'modal-alumno-320.png'),Buffer.from(shot.data,'base64'));
      await evaluate(`document.getElementById('modalNuevoAlumno').style.display='none'`);
    }
    const menu=await evaluate(`(()=>{const b=document.getElementById('staff-menu-toggle');b.click();const focusItems=[b,...document.querySelector('[data-staff-sidebar]').querySelectorAll('a[href],button:not([disabled]),input:not([type=hidden]),select,[tabindex="0"]')].filter(e=>e.getClientRects().length&&!e.disabled);focusItems.at(-1).focus();document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true}));const trapped=document.activeElement===b;const active=document.activeElement.outerHTML.slice(0,160),last=focusItems.at(-1).outerHTML.slice(0,160);const closeButton=document.querySelector('.staff-menu-close'),cr=closeButton.getBoundingClientRect();const hit=document.elementFromPoint(cr.left+cr.width/2,cr.top+cr.height/2);const reachable=closeButton===hit||closeButton.contains(hit);const opened=b.getAttribute('aria-expanded')==='true'&&!document.querySelector('[data-staff-sidebar]').inert&&document.querySelector('[data-staff-panel]').inert;document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));const closed=b.getAttribute('aria-expanded')==='false'&&document.querySelector('[data-staff-sidebar]').inert&&!document.querySelector('[data-staff-panel]').inert;b.click();document.getElementById('staff-menu-backdrop').click();const backdrop=b.getAttribute('aria-expanded')==='false';b.click();document.querySelector('.staff-menu-close').click();b.click();const a=document.querySelector('[data-staff-sidebar] a[href]');a.addEventListener('click',e=>e.preventDefault(),{once:true});a.click();return {label:b.getAttribute('aria-label')==='Abrir men\u00fa de navegaci\u00f3n',opened,closed,backdrop,trapped,active,last,reachable,hit:hit?.id||hit?.tagName,layers:[...document.querySelector('[data-staff-shell]').children].map(e=>({tag:e.tagName,id:e.id,z:getComputedStyle(e).zIndex,pos:getComputedStyle(e).position})),link:b.getAttribute('aria-expanded')==='false',focus:document.activeElement===b}})()`);
    if(!menu.opened||!menu.closed||!menu.backdrop||!menu.link||!menu.trapped||!menu.reachable||!menu.label)errors.push('menu '+JSON.stringify(menu));
   } else if(state.toggle!=='none'||state.sidebar==='hidden')errors.push('desktop navigation');
   if(errors.length)failures.push({file,width,errors,state});
   results.push({file,width,...state});
   if(['admin-dashboard.html','docente-asistencia.html','docente-notas.html','historial.html','reporte-asistencias.html','asistencia-mensual.html','asistencia-anual.html','cursos-mensual.html','cursos-anual.html'].includes(file)){
    const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,file.replace('.html','')+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
   }
  }
 }
 fs.writeFileSync(path.join(dir,process.argv[2]?'browser-debug.json':'browser-results.json'),JSON.stringify({checks:results.length,failures,results},null,2));
 console.log(JSON.stringify({checks:results.length,failures},null,2));
 if(failures.length)process.exitCode=1;
 }finally { await send('Browser.close').catch(()=>{});ws.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;browser.kill();});
