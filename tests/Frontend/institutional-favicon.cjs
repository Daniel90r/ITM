// Requiere fixtures: ITM_EXPORT_FAVICON_FIXTURES=1 + InstitutionalFaviconTest.
// HTTP aislado: solo metadatos sintéticos y cuatro iconos públicos. No inicia Laravel.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {spawn} = require('node:child_process');
const assert = require('node:assert/strict');
const directory = path.resolve('storage/app/reportes-referencia/favicon');
const fixtures = fs.readdirSync(directory).filter(file => file.endsWith('.html'));
const assets = new Map([
  ['/favicon.ico', ['public/favicon.ico', 'image/x-icon']],
  ...[32, 180, 192].map(size => ['/favicons/itm-'+size+'.png', ['public/favicons/itm-'+size+'.png', 'image/png']]),
  ['/visor-prueba.pdf', [path.join(directory, 'visor-prueba.pdf'), 'application/pdf']],
]);
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  const asset = assets.get(url.pathname);
  const name = url.pathname.slice(1);
  if (asset) { response.writeHead(200, {'Content-Type':asset[1]}); response.end(fs.readFileSync(asset[0])); }
  else if (fixtures.includes(name)) {
    // Normalizar únicamente el origen de los iconos del fixture, nunca rutas de la app.
    const html = fs.readFileSync(path.join(directory, name), 'utf8').replace(/href="https?:\/\/[^/]+\//g, 'href="/');
    response.writeHead(200, {'Content-Type':'text/html; charset=utf-8'}); response.end(html);
  } else {response.writeHead(404); response.end();}
});
(async () => {
  assert.ok(fixtures.length >= 10, 'Ejecutar primero las pruebas PHP con exportación');
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = 'http://127.0.0.1:'+server.address().port;
  const results = [], pdfResults = [];
  try {
    for (const [name, executable] of [
      ['Chrome', 'C:/Program Files/Google/Chrome/Application/chrome.exe'],
      ['Edge', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'],
    ]) {
      assert.ok(fs.existsSync(executable), name+' instalado');
      const profile = fs.mkdtempSync(path.join(directory, name+'-'));
      const browser = spawn(executable, ['--headless=new', '--remote-debugging-port=0', '--user-data-dir='+profile, '--no-first-run', '--no-default-browser-check', '--disable-background-networking', 'about:blank'], {windowsHide:true, stdio:'ignore'});
      let startupError; browser.on('error', error => {startupError = error;});
      let info;
      for (let i=0; i<100; i++) {
        if (startupError) throw startupError;
        try {
          const port = Number(fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0]);
          info = await (await fetch('http://127.0.0.1:'+port+'/json/new?about:blank', {method:'PUT'})).json(); break;
        } catch {await pause(100);}
      }
      assert.ok(info, name+' inicia');
      const ws = new WebSocket(info.webSocketDebuggerUrl);
      await new Promise(resolve => ws.addEventListener('open', resolve, {once:true}));
      let id = 0; const pending = new Map();
      ws.addEventListener('message', event => {
        const message = JSON.parse(event.data), waiter = pending.get(message.id);
        if (waiter) {pending.delete(message.id); message.error ? waiter.reject(message.error) : waiter.resolve(message.result);}
      });
      const send = (method, params={}) => new Promise((resolve,reject) => {const key=++id; pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});
      const evaluate = async expression => (await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true})).result.value;
      try {
        const version = await send('Browser.getVersion');
        await send('Page.enable');
        for (const width of [320, 390, 768, 1440]) {
          await send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:width<768});
          for (const file of fixtures) {
            const url = base+'/'+file;
            await send('Page.navigate', {url});
            for (let i=0; i<100; i++) {if (await evaluate('location.href==='+JSON.stringify(url)+' && document.readyState==="complete"')) break;await pause(30);}
            const icons = await evaluate(`Promise.all([...document.querySelectorAll('link[rel="icon"],link[rel="apple-touch-icon"]')].map(async link=>{const response=await fetch(link.href);const image=new Image();image.src=link.href;try{await image.decode();return {ok:response.ok,type:response.headers.get('content-type'),width:image.naturalWidth,height:image.naturalHeight,declared:link.sizes.value};}catch{return {ok:false};}}))`);
            assert.equal(icons.length, 4, name+' '+file);
            for (const icon of icons) {assert.ok(icon.ok, JSON.stringify(icon));assert.equal(icon.width, icon.height);assert.ok(icon.width>=16);}
            results.push({browser:name, version:version.product, file, width, icons:icons.length});
          }
        }
        const pdfResponse = await fetch(base+'/visor-prueba.pdf');
        assert.equal(pdfResponse.headers.get('content-type'), 'application/pdf');
        assert.ok((await pdfResponse.text()).startsWith('%PDF-'));
        await send('Page.navigate', {url:base+'/visor-prueba.pdf'});
        await pause(1200);
        const pdfState = await evaluate(`({url:location.href,type:document.contentType,embedded:!!document.querySelector('embed[type="application/pdf"]'),htmlIconLinks:document.querySelectorAll('link[rel="icon"]').length})`);
        assert.equal(pdfState.url, base+'/visor-prueba.pdf');
        assert.ok(pdfState.type === 'application/pdf' || pdfState.embedded, name+' visor PDF');
        pdfResults.push({browser:name,...pdfState});
      } finally {await send('Browser.close').catch(()=>{});ws.close();}
    }
    fs.writeFileSync(path.join(directory, 'browser-results.json'), JSON.stringify(results, null, 2));
    fs.writeFileSync(path.join(directory, 'pdf-viewer-results.json'), JSON.stringify(pdfResults, null, 2));
    console.log(JSON.stringify({checks:results.length,browsers:[...new Set(results.map(r=>r.browser+' '+r.version))],widths:[320,390,768,1440],pdfViewers:pdfResults,failures:0}));
  } finally {await new Promise(resolve => server.close(resolve));}
})().catch(error => {console.error(error);process.exitCode=1;});
