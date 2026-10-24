import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const code = readFileSync(new URL('../../resources/js/admin/estado-persona.js', import.meta.url), 'utf8');
function setup({ disabled = false, confirm = true, loading = false, valid = true, accion = 'desactivar', failure = false } = {}) {
    const handlers = {};
    const reason = { value: 'Motivo administrativo', maxLength: 500, addEventListener: (name, fn) => { handlers[name] = fn; } };
    const counter = { textContent: '' }, button = { disabled }, label = { textContent: 'Desactivar acceso' }, status = { textContent: '' };
    const nodes = { '[data-persona-reason]': reason, '[data-persona-counter]': counter, 'button[type="submit"]': button, '[data-persona-button-label]': label, '[data-persona-status]': status };
    const form = { dataset: { personaAccion: accion, personaNombre: 'Persona de prueba', personaConfirm: 'Finalizar asignación de Persona de prueba' }, attrs: {}, sends: 0, reportValidity: () => valid, querySelector: s => nodes[s], addEventListener: (name, fn) => { handlers[name] = fn; }, setAttribute(name, value) { this.attrs[name] = value; }, removeAttribute(name) { delete this.attrs[name]; } };
    const confirmations = [];
    const document = { readyState: loading ? 'loading' : 'complete', querySelectorAll: () => [form], addEventListener: (name, fn) => { handlers[name] = fn; } };
    const window = { mostrarConfirmacion: async options => { confirmations.push(options); if (failure) throw Error('Modal no disponible'); return {isConfirmed: confirm}; }, addEventListener: (name, fn) => { handlers[name] = fn; } };
    const HTMLFormElement = {prototype: {submit() { this.sends++; }}};
    vm.runInNewContext(code, { document, window, HTMLFormElement });
    if (loading) handlers.DOMContentLoaded();
    const submit = async () => { const e = { prevented: false, preventDefault() { this.prevented = true; } }; await handlers.submit(e); return e; };
    return { handlers, reason, counter, button, label, status, form, confirmations, submit, window };
}
test('contador inicial y cambios sin alterar el motivo', () => {
    const x = setup({ loading: true });
    assert.equal(x.counter.textContent, '21 / 500');
    x.reason.value = 'Nuevo motivo'; x.handlers.input();
    assert.equal(x.counter.textContent, '12 / 500'); assert.equal(x.reason.value, 'Nuevo motivo');
});
test('cancelar confirma la persona y permite volver a editar', async () => {
    const x = setup({ confirm: false });
    assert.equal((await x.submit()).prevented, true); assert.match(x.confirmations[0].mensaje, /Persona de prueba/);
    assert.equal(x.form.sends, 0);
    assert.equal(x.button.disabled, false); assert.equal(x.form.dataset.processing, undefined);
});
test('envío nativo, procesamiento accesible y protección de doble envío', async () => {
    const x = setup();
    assert.equal((await x.submit()).prevented, true); assert.equal(x.button.disabled, true); assert.equal(x.form.sends, 1);
    assert.equal(x.form.attrs['aria-busy'], 'true'); assert.match(x.status.textContent, /Enviando/);
    assert.equal((await x.submit()).prevented, true); assert.equal(x.confirmations.length, 1); assert.equal(x.form.sends, 1);
    x.handlers.pageshow(); assert.equal(x.button.disabled, false); assert.equal(x.label.textContent, 'Desactivar acceso');
    assert.equal(x.status.textContent, ''); assert.equal(x.form.attrs['aria-busy'], undefined);
});
test('un bloqueo original de asignaciones nunca se habilita', async () => {
    const x = setup({ disabled: true });
    assert.equal((await x.submit()).prevented, true); assert.equal(x.confirmations.length, 0);
    x.handlers.pageshow(); assert.equal(x.button.disabled, true);
});
test('desactivar y reactivar adaptan título, persona, conservación y estilo', async () => {
    for (const accion of ['desactivar', 'reactivar']) {
        const x = setup({accion}); await x.submit(); const options = x.confirmations[0];
        assert.equal(options.titulo, accion === 'reactivar' ? '¿Reactivar el acceso?' : '¿Desactivar el acceso?');
        assert.equal(options.boton, `Sí, ${accion}`); assert.match(options.mensaje, /Persona de prueba/);
        assert.match(options.mensaje, /registros académicos y económicos se conservan/);
        assert.equal(options.tipo, accion === 'reactivar' ? 'exito' : 'peligro');
        assert.equal(options.opciones.focusCancel, true); assert.equal(options.opciones.allowEscapeKey, true);
    }
});
test('motivo inválido y error del modal nunca envían', async () => {
    const invalid = setup({valid: false}); await invalid.submit();
    assert.equal(invalid.confirmations.length, 0); assert.equal(invalid.form.sends, 0);
    const failure = setup({failure: true}); await failure.submit();
    assert.equal(failure.form.sends, 0); assert.equal(failure.button.disabled, false); assert.match(failure.status.textContent, /No se pudo/);
});
test('mientras se confirma no abre otro modal ni duplica el envío', async () => {
    const x = setup(); let resolve;
    x.window.mostrarConfirmacion = () => new Promise(r => {resolve = r;});
    const pending = x.submit(); await x.submit(); assert.equal(x.form.sends, 0);
    resolve({isConfirmed: true}); await pending; assert.equal(x.form.sends, 1);
});
test('si no está disponible la confirmación institucional no recurre a confirm nativo ni envía', async () => {
    const x = setup(); delete x.window.mostrarConfirmacion;
    await x.submit(); assert.equal(x.form.sends, 0); assert.match(x.status.textContent, /No se pudo abrir/);
});
test('las opciones visuales específicas no cambian las confirmaciones existentes', async () => {
    const source = readFileSync(new URL('../../resources/js/admin/alertas.js', import.meta.url), 'utf8')
        .replace(/^import .*;\s*/m, '').replace(/^export .*;\s*/m, '');
    const calls = [], window = {};
    vm.runInNewContext(source, {window, document: {addEventListener() {}}, Swal: {fire(options) {calls.push(options); return Promise.resolve({isConfirmed: false});}}});
    await window.mostrarConfirmacion({mensaje: 'Confirmación existente'});
    assert.equal(calls[0].confirmButtonColor, '#111827'); assert.equal(calls[0].cancelButtonText, 'Cancelar');
    await window.mostrarConfirmacion({mensaje: '<nombre literal>', opciones: {confirmButtonColor: '#991b1b', focusCancel: true}});
    assert.equal(calls[1].confirmButtonColor, '#991b1b'); assert.equal(calls[1].text, '<nombre literal>');
    assert.equal(calls[1].html, undefined); assert.equal(calls[1].focusCancel, true);
});
