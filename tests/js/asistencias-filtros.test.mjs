import test from 'node:test';
import assert from 'node:assert/strict';
import {initFiltrosAsistencias} from '../../resources/js/admin/asistencias-filtros.js';

const tick = () => new Promise(resolve => setImmediate(resolve));
function entorno(responder = async url => ({ok:true,status:200,text:async () => url}), conAlcance = false) {
    const handlers = {}, clicks = {}, windows = {}, requests = [], pushes = [];
    const controls = ['q','curso_id','detalle_curso_id','maestro_id','anio','mes','fecha_desde','fecha_hasta'].map(name => ({name,value:'',type:'text'}));
    if (conAlcance) controls.push({name:'incluir_historicos',value:'0',type:'hidden'}, {name:'incluir_historicos',value:'1',type:'checkbox',checked:false});
    const results = {innerHTML:'inicial',setAttribute(k,v) {this[k]=v;},focus() {}}, loading={textContent:''},error={textContent:''};
    const form = {dataset:{},action:'http://itm.test/admin/asistencias',querySelectorAll:()=>controls,querySelector:s=>s.includes('checkbox') ? controls.find(c=>c.type==='checkbox') : controls[2],addEventListener:(e,fn)=>handlers[e]=fn};
    const nodes = {'[data-asistencias-filtros]':form,'[data-asistencias-resultados]':results,'[data-asistencias-carga]':loading,'[data-asistencias-error]':error};
    for(const selector of ['[data-asistencias-materias]','[data-asistencia-exportacion]','[data-asistencia-periodo]']) nodes[selector]={innerHTML:'anterior',hidden:false};
    const root={querySelector:s=>nodes[s],addEventListener:(e,fn)=>clicks[e]=fn};
    globalThis.location = new URL(form.action);
    globalThis.history = {pushState:(_,__,url)=>{pushes.push(url);globalThis.location=new URL(url);}};
    globalThis.window = {addEventListener:(e,fn)=>windows[e]=fn};
    globalThis.FormData = class {constructor(){return controls.filter(x=>x.value!==''&&(x.type!=='checkbox'||x.checked)).map(x=>[x.name,x.value]);}};
    globalThis.DOMParser = class {parseFromString(html) {return {querySelector:s=>s.includes('checkbox') ? (conAlcance?{checked:new URL(html).searchParams.get('incluir_historicos')==='1'}:null) : s==='[data-asistencias-resultados]'?{innerHTML:html}:{innerHTML:'actualizado',hidden:false}};}};
    globalThis.fetch=async (url,options)=>{requests.push({url,options});return responder(url,options);};
    initFiltrosAsistencias(root);
    return {controls,results,loading,error,requests,pushes,nodes,form,
        submit(){let prevented=false;handlers.submit({preventDefault(){prevented=true;}});return prevented;},
        click(url,clear=false){let prevented=false;clicks.click({button:0,target:{closest:s=>s===(clear?'[data-asistencias-limpiar]':'[data-asistencias-resultados] .listado-footer a')?{href:url}:null},preventDefault(){prevented=true;}});return prevented;},
        back(url){globalThis.location=new URL(url);windows.popstate();},
    };
}

test('filtra sin navegar y conserva año, mes y URL aplicada', async () => {
    const e=entorno();e.controls[4].value='2026';e.controls[5].value='10';
    assert.equal(e.submit(),true);await tick();
    assert.match(e.results.innerHTML,/anio=2026&mes=10/);assert.equal(e.controls[5].value,'10');
    assert.equal(e.pushes.length,1);assert.equal(e.results['aria-busy'],'false');
    assert.equal(e.requests[0].options.credentials,'same-origin');
});

test('el alcance histórico se conserva al paginar y se restaura con atrás y limpiar', async () => {
    const e=entorno(undefined,true), check=e.controls.find(c=>c.type==='checkbox');
    check.checked=true;e.submit();await tick();
    assert.equal(new URL(e.requests[0].url).searchParams.get('incluir_historicos'),'1');
    e.click('http://itm.test/admin/asistencias?incluir_historicos=1&page=2');await tick();
    assert.equal(check.checked,true);
    e.back('http://itm.test/admin/asistencias?incluir_historicos=0');await tick();
    assert.equal(check.checked,false);
    e.click(e.form.action,true);await tick();assert.equal(check.checked,false);
    assert.equal(e.controls.find(c=>c.type==='hidden').value,'0');
});
test('paginar y limpiar solo actualiza resultados y controles', async () => {
    const e=entorno();e.click('http://itm.test/admin/asistencias?anio=2025&page=2');await tick();
    assert.equal(e.controls[4].value,'2025');assert.match(e.results.innerHTML,/page=2/);
    e.click(e.form.action,true);await tick();assert.ok(e.controls.every(x=>x.value===''));assert.equal(location.search,'');
});
test('atrás y adelante restauran filtros sin crear entradas de historial', async () => {
    const e=entorno();e.back('http://itm.test/admin/asistencias?anio=2024&mes=9');await tick();
    assert.equal(e.controls[4].value,'2024');assert.equal(e.controls[5].value,'9');assert.equal(e.pushes.length,0);
    e.back('http://itm.test/admin/asistencias?anio=2026');await tick();assert.equal(e.controls[4].value,'2026');assert.equal(e.controls[5].value,'');
});
test('descarta respuestas antiguas aunque el servidor ignore la cancelación', async () => {
    const pending=[];const e=entorno(()=>new Promise(resolve=>pending.push(resolve)));
    e.controls[4].value='2025';e.submit();e.submit();assert.equal(e.requests.length,1);
    e.controls[4].value='2026';e.submit();assert.equal(e.requests[0].options.signal.aborted,true);
    pending[1]({ok:true,status:200,text:async()=> 'nueva'});await tick();
    pending[0]({ok:true,status:200,text:async()=> 'antigua'});await tick();
    assert.equal(e.results.innerHTML,'nueva');assert.equal(location.search,'?anio=2026');
});
test('muestra validación sin perder resultados ni modificar URL', async () => {
    const e=entorno(async()=>({status:422,json:async()=>({errors:{anio:['Selecciona un año válido.']}})}));
    e.controls[5].value='10';e.submit();await tick();assert.match(e.error.textContent,/año válido/);
    assert.equal(e.results.innerHTML,'inicial');assert.equal(e.pushes.length,0);assert.equal(e.controls[5].value,'10');
});
test('sesión vencida y fallos de red no insertan contenido no autorizado', async () => {
    const e=entorno(async()=>({status:401,ok:false}));e.submit();await tick();
    assert.match(e.error.textContent,/sesión expiró/);assert.equal(e.results.innerHTML,'inicial');
    const f=entorno(async()=>{throw Error('network');});f.submit();await tick();assert.match(f.error.textContent,/Inténtalo/);assert.equal(f.results['aria-busy'],'false');
});
