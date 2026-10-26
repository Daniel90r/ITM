import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../resources/js/admin/cursos.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '');
function setup(confirm = async () => ({isConfirmed: false}), failSubmit = false) {
    let submit, options, count = 0, calls = 0;
    const button = {textContent: 'Finalizar curso', disabled: false}, error = {textContent: ''}, pages = [];
    const form = {dataset: {confirmTitulo: '¿Estás seguro de finalizar este curso?', finalizarConfirm: 'El curso cambiará al estado Finalizado. ¿Deseas continuar?', confirmBoton: 'Sí, finalizar curso'},
        querySelector: s => s.startsWith('button') ? button : error, reportValidity: () => true,
        setAttribute() {}, removeAttribute() {}, addEventListener: (_, fn) => submit = fn};
    const ready = [];
    vm.runInNewContext(source, {document: {addEventListener: (_, fn) => ready.push(fn), querySelectorAll: () => [form], querySelector: () => null, getElementById: () => null},
        window: {addEventListener: (_, fn) => pages.push(fn), mostrarConfirmacion: async o => {options = o; calls++; return confirm();}},
        HTMLFormElement: {prototype: {submit() {if (failSubmit) throw Error('Network'); count++;}}}, Error});
    ready.forEach(fn => fn());
    return {form, button, error, pages, run: () => submit({preventDefault() {}}), get count() {return count;}, get calls() {return calls;}, get options() {return options;}};
}

test('cancelación no envía y permite un nuevo intento', async () => {
    const c = setup(); await c.run(); await c.run();
    assert.equal(c.count, 0); assert.equal(c.calls, 2); assert.equal(c.button.disabled, false);
    assert.equal(c.options.titulo, '¿Estás seguro de finalizar este curso?');
    assert.equal(c.options.mensaje, 'El curso cambiará al estado Finalizado. ¿Deseas continuar?');
    assert.equal(c.options.boton, 'Sí, finalizar curso');
});
test('confirmación y repetición envían exactamente una vez', async () => {
    const c = setup(async () => ({isConfirmed: true})); await c.run(); await c.run();
    assert.equal(c.count, 1); assert.equal(c.calls, 1); assert.equal(c.button.disabled, true);
});
test('mientras la ventana está abierta no se abre una segunda', async () => {
    let finish; const c = setup(() => new Promise(resolve => finish = resolve));
    const first = c.run(); await c.run(); assert.equal(c.calls, 1);
    finish({isConfirmed: false}); await first; assert.equal(c.count, 0);
});
test('error de confirmación muestra mensaje y desbloquea', async () => {
    const c = setup(async () => {throw Error('Unavailable');}); await c.run();
    assert.match(c.error.textContent, /No se pudo enviar/); assert.equal(c.button.disabled, false); assert.equal(c.form.dataset.finalizarBusy, undefined);
});
test('error de envío permite reintentar', async () => {
    const c = setup(async () => ({isConfirmed: true}), true); await c.run(); await c.run();
    assert.equal(c.count, 0); assert.equal(c.calls, 2); assert.equal(c.button.disabled, false); assert.match(c.error.textContent, /Inténtalo/);
});
test('regresar mediante caché del navegador restablece el botón', async () => {
    const c = setup(async () => ({isConfirmed: true})); await c.run(); c.pages.forEach(fn => fn());
    assert.equal(c.button.disabled, false); assert.equal(c.form.dataset.finalizarBusy, undefined);
});
