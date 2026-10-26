import test from 'node:test';
import assert from 'node:assert/strict';
import {initFiltrosAlumnos} from '../../resources/js/admin/alumnos-filtros.js';
const tick=()=>new Promise(r=>setImmediate(r));

function entorno(responder=async url=>({ok:true,status:200,text:async()=>url})) {
    const handlers={},clicks={},windows={},requests=[],pushes=[];
    const controls=['curso_id','anio','q'].map(name=>({name,value:'',type:'text',removeAttribute(){}}));
    const checkbox={name:'incluir_historicos',type:'checkbox',value:'1',checked:false,removeAttribute(){}};
    controls.push({name:'incluir_historicos',type:'hidden',value:'0',removeAttribute(){}},checkbox);
    controls[0].selectedOptions=[{dataset:{estadoCurso:'ACTIVO'}}];
    const form={dataset:{},action:'http://itm.test/admin/alumnos',querySelectorAll:()=>controls,
        querySelector:s=>s.includes('checkbox')?checkbox:controls.find(c=>s.includes(`"${c.name}"`)),addEventListener:(e,fn)=>handlers[e]=fn};
    const loading={textContent:''},error={textContent:''};
    const nodes={'#alumnos-alcance':form,'[data-alumnos-carga]':loading,'[data-alumnos-error]':error,'.alumnos-limpiar':{removeAttribute(){}}};
    for(const s of ['[data-alumnos-resultados]','[data-alumnos-resumen]','[data-alumnos-contador]','#alumnos-curso-consulta','#filtrarAnio'])nodes[s]={innerHTML:'inicial',setAttribute(k,v){this[k]=v}};
    const sidebar={},root={querySelector:s=>nodes[s],addEventListener:(e,fn)=>clicks[e]=fn,sidebar};
    globalThis.location=new URL(form.action);globalThis.history={pushState:(_,__,url)=>{pushes.push(url);globalThis.location=new URL(url)}};
    globalThis.window={addEventListener:(e,fn)=>windows[e]=fn};
    globalThis.FormData=class {constructor(){return controls.filter(c=>c.type!=='checkbox'||c.checked).map(c=>[c.name,c.value])}};
    globalThis.DOMParser=class {parseFromString(html){const u=new URL(html);return {querySelector:s=>{
        if(s==='#alumnos-alcance')return {querySelector:s=>{const name=s.match(/name="([^"]+)"/)?.[1];if(s.includes('checkbox')&&name!=='incluir_historicos')return null;return {value:u.searchParams.get(name)||'',checked:u.searchParams.get('incluir_historicos')==='1'||u.searchParams.get('curso_id')==='9'}}};
        return {innerHTML:html};
    }}}};
    globalThis.fetch=async(url,options)=>{requests.push({url,options});return responder(url,options)};
    initFiltrosAlumnos(root);
    return {controls,checkbox,form,nodes,loading,error,requests,pushes,root,sidebar,
        change(c){handlers.change({target:c})},input(){handlers.input({target:controls[2]})},submit(){handlers.submit({preventDefault(){}})},
        back(url){globalThis.location=new URL(url);windows.popstate()},
        click(url,clear=false){clicks.click({button:0,target:{closest:s=>s===(clear?'.alumnos-limpiar':'[data-alumnos-resultados] a[data-page], [data-alumnos-resultados] .pagination a, [data-alumnos-resultados] nav a')?{href:url}:null},preventDefault(){}})},
    };
}

test('curso actualiza estadísticas y tabla una vez, sin sustituir el menú',async()=>{
    const e=entorno();e.controls[0].value='1';e.controls[1].value='2025';e.controls[2].value='Ana';e.change(e.controls[0]);e.submit();await tick();
    assert.equal(e.requests.length,1);assert.match(e.nodes['[data-alumnos-resumen]'].innerHTML,/curso_id=1&anio=2025&q=Ana/);
    assert.equal(e.root.sidebar,e.sidebar);assert.equal(e.requests[0].options.credentials,'same-origin');assert.equal(e.pushes.length,1);
});
test('debounce y escritura invalidan inmediatamente respuestas anteriores',async t=>{
    t.mock.timers.enable({apis:['setTimeout']});const pending=[],e=entorno(()=>new Promise(r=>pending.push(r)));
    e.controls[0].value='1';e.change(e.controls[0]);e.controls[2].value='Ma';e.input();
    assert.equal(e.requests[0].options.signal.aborted,true);pending[0]({ok:true,status:200,text:async()=> 'http://itm.test/admin/alumnos?curso_id=1'});await tick();
    assert.equal(e.nodes['[data-alumnos-resultados]'].innerHTML,'inicial');
    e.controls[2].value='Maria';e.input();t.mock.timers.tick(299);assert.equal(e.requests.length,1);t.mock.timers.tick(1);assert.equal(e.requests.length,2);
    pending[1]({ok:true,status:200,text:async()=>e.requests[1].url});await tick();assert.match(e.nodes['[data-alumnos-resultados]'].innerHTML,/q=Maria/);
});
test('histórico, paginación, atrás y limpieza conservan filtros',async()=>{
    const e=entorno();e.controls[0].value='9';e.change(e.controls[0]);await tick();assert.equal(e.checkbox.checked,true);assert.match(location.search,/incluir_historicos=1/);
    e.click('http://itm.test/admin/alumnos?incluir_historicos=1&page=2');await tick();assert.match(e.nodes['[data-alumnos-resultados]'].innerHTML,/page=2/);
    e.back('http://itm.test/admin/alumnos?anio=2024&incluir_historicos=1');await tick();assert.equal(e.controls[1].value,'2024');assert.equal(e.pushes.length,2);
    e.click(e.form.action,true);await tick();assert.equal(e.controls[0].value,'');assert.equal(e.controls[1].value,'');assert.equal(e.checkbox.checked,false);
});
test('desmarcar históricos limpia solo un curso finalizado',async()=>{
    const e=entorno();e.controls[0].value='9';e.controls[0].selectedOptions[0].dataset.estadoCurso='FINALIZADO';e.change(e.checkbox);await tick();assert.equal(e.controls[0].value,'');
    e.controls[0].value='1';e.controls[0].selectedOptions[0].dataset.estadoCurso='ACTIVO';e.change(e.checkbox);await tick();assert.equal(e.controls[0].value,'1');
});
test('validación, sesión vencida y red conservan resultados y URL',async()=>{
    for(const responder of [async()=>({status:422,json:async()=>({errors:{anio:['Año inválido.']}})}),async()=>({status:403}),async()=>{throw Error('red')}]){
        const e=entorno(responder);e.submit();await tick();assert.equal(e.nodes['[data-alumnos-resultados]'].innerHTML,'inicial');assert.equal(e.pushes.length,0);assert.ok(e.error.textContent);assert.equal(e.nodes['[data-alumnos-resultados]']['aria-busy'],'false');
    }
});
