import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../resources/js/admin/inscritos-estado.js', import.meta.url), 'utf8');
function setup(accion, confirmation = async () => ({isConfirmed: false})) {
    let handler, options, submissions = 0, calls = 0;
    const button = {textContent: accion, disabled: false}, reason = {value: ''}, error = {textContent: ''};
    const form = {dataset: {accion, nombre: 'Alumno de prueba'},
        querySelector: q => q.startsWith('button') ? button : q.includes('motivo') ? reason : error,
        addEventListener: (_, cb) => handler = cb, setAttribute() {}, removeAttribute() {}};
    const context = {document: {addEventListener: (_, cb) => cb(), querySelectorAll: () => [form]},
        window: {addEventListener() {}, mostrarConfirmacion: async o => {options = o; calls++;return confirmation();}},
        HTMLFormElement: {prototype: {submit() {submissions++;}}}, Error};
    vm.runInNewContext(source, context);
    return {form, button, reason, error, run: () => handler({preventDefault() {}}), get options() {return options;}, get submissions() {return submissions;}, get calls() {return calls;}};
}

for (const accion of ['activar','desactivar']) {
    test(`${accion}: texto correcto y cancelar no envía`, async () => {
        const c = setup(accion); await c.run();
        assert.equal(c.options.titulo, `¿Estás seguro de ${accion} a este alumno?`);
        assert.equal(c.submissions, 0); assert.equal(c.button.disabled, false);
        assert.equal(c.reason.value, '');
    });
}
test('confirmar envía una vez y conserva el motivo', async () => {
    const c = setup('desactivar', async () => ({isConfirmed:true,value:'Motivo administrativo'}));
    await c.run(); await c.run();
    assert.equal(c.submissions,1); assert.equal(c.calls,1); assert.equal(c.reason.value,'Motivo administrativo');
    assert.equal(c.button.disabled,true);
});
test('impide diálogos simultáneos', async () => {
    let finish; const c = setup('activar', () => new Promise(resolve => finish=resolve));
    const pending = c.run(); await c.run(); assert.equal(c.calls,1);
    finish({isConfirmed:false}); await pending; assert.equal(c.submissions,0);
});
test('valida motivo y permite reintento ante error', async () => {
    const c = setup('activar'); await c.run();
    assert.ok(c.options.opciones.inputValidator('corto'));
    assert.equal(c.options.opciones.inputValidator('Motivo correcto'),undefined);
    const broken = setup('activar', async () => {throw Error('Fallo');}); await broken.run(); await broken.run();
    assert.equal(broken.calls,2); assert.equal(broken.submissions,0); assert.equal(broken.button.disabled,false);
    assert.match(broken.error.textContent,/No se pudo enviar/);
});
