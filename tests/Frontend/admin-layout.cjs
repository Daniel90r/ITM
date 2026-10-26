// Ejecutar solo contra HTML sintético generado por StaffResponsiveTest.
const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const assert = require('node:assert/strict');
const dir = process.env.ITM_ADMIN_LAYOUT_FIXTURES;
const height = Number(process.env.ITM_ADMIN_LAYOUT_HEIGHT || 900);
if (!dir) throw Error('ITM_ADMIN_LAYOUT_FIXTURES debe apuntar a vistas aisladas.');
const profile = fs.mkdtempSync(path.join(dir, 'chrome-layout-'));
const browser = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--remote-debugging-port=0','--user-data-dir='+profile,'--no-first-run','--no-default-browser-check','--disable-background-networking','about:blank'], {windowsHide:true,stdio:'ignore'});
const pause = ms => new Promise(r => setTimeout(r,ms));
browser.on('error',e=>{console.error(e.message);process.exitCode=1;});
(async()=>{
    let tabs;
    for(let i=0;i<80;i++) {try {const port=Number(fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0]);tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();break;}catch{await pause(100);}}
    assert.ok(tabs,'No se pudo iniciar Chrome de pruebas');
    const ws = new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});
    let id=0;const pending=new Map();
    ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const checks=[],failures=[];
    try {
        await send('Page.enable');await send('Network.enable');
        await send('Network.setBlockedURLs',{urls:['http://localhost/*','http://itm.test/*','https://*']});
        const files=fs.readdirSync(dir).filter(f=>f.endsWith('.html')&&!f.startsWith('docente-')&&(!process.argv[2]||f===process.argv[2]));
        for(const file of files) {
            for(const width of [320,375,390,768,1440]) {
                await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
                await send('Page.navigate',{url:'http://127.0.0.1:8766/fixtures/'+file});
                for(let i=0;i<60;i++){await pause(40);if(await evaluate(`document.body?.hasAttribute('data-staff-ready') && location.pathname.endsWith(${JSON.stringify(file)})`))break;}
                const result=await evaluate(`(()=>{
                    const shell=document.querySelector('[data-staff-shell]'),panel=document.querySelector('[data-staff-panel]'),sidebar=document.querySelector('[data-staff-sidebar]');
                    if(!shell||!panel||!sidebar)return {missing:true};
                    const scrollables=[panel,...panel.querySelectorAll('div,section,main')].filter(e=>['auto','scroll'].includes(getComputedStyle(e).overflowY)&&e.scrollHeight>e.clientHeight+1);
                    scrollables.forEach(e=>e.scrollTop=e.scrollHeight);
                    const targets=[...panel.querySelectorAll('tr,p,a,button,input')].filter(e=>e.getClientRects().length&&!e.closest('[hidden]'));
                    targets.at(-1)?.scrollIntoView({block:'end'});
                    shell.scrollTop=400;window.scrollTo(0,400);
                    const sr=shell.getBoundingClientRect(),pr=panel.getBoundingClientRect();
                    const chains=scrollables.filter(e=>e.contains(targets.at(-1))).map(e=>e.className||e.tagName);
                    let menu=true,menuDebug={};
                    if(innerWidth<1024){const toggle=document.getElementById('staff-menu-toggle');toggle.click();sidebar.scrollTop=sidebar.scrollHeight;const close=sidebar.querySelector('.staff-menu-close');const cr=close.getBoundingClientRect(),hit=document.elementFromPoint(cr.left+10,cr.top+10);menu=sidebar.getBoundingClientRect().bottom>=innerHeight-1&&(hit===close||close.contains(hit));document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));menu=menu&&toggle.getAttribute('aria-expanded')==='false';}
                    else{sidebar.scrollTop=sidebar.scrollHeight;menu=sidebar.getBoundingClientRect().top===0&&sidebar.clientHeight===innerHeight;}
                    menuDebug={rect:sidebar.getBoundingClientRect().toJSON(),height:sidebar.clientHeight,scroll:sidebar.scrollTop,hidden:sidebar.inert,expanded:document.getElementById('staff-menu-toggle').getAttribute('aria-expanded')};
                    const modal=document.querySelector('[data-staff-modal]');let modalFits=true;
                    if(modal){const original=modal.getAttribute('style'),hidden=modal.classList.contains('hidden');modal.classList.remove('hidden');modal.style.display='flex';const rect=modal.getBoundingClientRect();modalFits=rect.left>=-1&&rect.right<=innerWidth+1&&rect.top>=-1&&rect.bottom<=innerHeight+1;if(original===null)modal.removeAttribute('style');else modal.setAttribute('style',original);if(hidden)modal.classList.add('hidden');}
                    return {admin:document.body.classList.contains('itm-admin'),body:getComputedStyle(document.body).backgroundColor,shellOverflow:getComputedStyle(shell).overflow,shellScroll:shell.scrollTop,windowY:scrollY,documentHeight:document.documentElement.scrollHeight,documentWidth:document.documentElement.scrollWidth,shellTop:sr.top,shellBottom:sr.bottom,panelTop:pr.top,panelBottom:pr.bottom,chains,menu,menuDebug,modalFits};
                })()`);
                const errors=[];
                if(result.missing)errors.push('missing shell');else{
                    if(!result.admin||result.body!=='rgb(248, 250, 252)')errors.push('background');
                    if(result.shellScroll!==0||result.windowY!==0)errors.push('outer scroll');
                    if(result.documentWidth>width+1||result.documentHeight>height+1)errors.push('document overflow');
                    if(Math.abs(result.shellTop-(width<1024?60:0))>1||Math.abs(result.shellBottom-height)>1||Math.abs(result.panelTop-result.shellTop)>1||Math.abs(result.panelBottom-height)>1)errors.push('viewport gap');
                    if(!result.menu)errors.push('menu');if(!result.modalFits)errors.push('modal');if(result.chains.length>1)errors.push('nested vertical scroll');
                }
                const item={file,width,errors,...result};checks.push(item);if(errors.length)failures.push(item);
                if(['admin-asistencias.html','asistencias-vacias.html','admin-pagos.html','admin-dashboard.html','curso-editar.html'].includes(file)){
                    const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,file.replace('.html','')+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
                }
            }
            console.log(JSON.stringify({file,checked:5,failures:failures.filter(f=>f.file===file).length}));
        }
        fs.writeFileSync(path.join(dir,process.argv[2]?'layout-'+process.argv[2]+'.json':'layout-results.json'),JSON.stringify({height,checks:checks.length,failures,results:checks},null,2));
        console.log(JSON.stringify({checks:checks.length,failures}));if(failures.length)process.exitCode=1;
    } finally {await send('Browser.close').catch(()=>{});ws.close();}
})().catch(e=>{console.error(e);process.exitCode=1;browser.kill();});
