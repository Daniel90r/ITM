import assert from 'node:assert/strict';
import { test } from 'node:test';

globalThis.document = { querySelectorAll: () => [] };
const { iniciarFirmaDirector } = await import('../../resources/js/admin/firma-director.js');

function componente() {
    const puntos = [];
    function elemento(extra = {}) {
        return { listeners: {}, addEventListener(tipo, fn) { this.listeners[tipo] = fn; }, ...extra };
    }
    const canvas = elemento({
        width: 800, height: 240,
        getBoundingClientRect: () => ({ left: 10, top: 20, width: 400, height: 120 }),
        getContext: () => ({
            beginPath() {}, arc() {}, fill() {}, stroke() {},
            moveTo: (...p) => puntos.push(p), lineTo: (...p) => puntos.push(p),
            clearRect: () => puntos.push('limpio'),
        }),
        setPointerCapture() {}, toDataURL: () => 'data:image/png;base64,trazos',
    });
    const modo = elemento({ value: 'archivo', setCustomValidity(mensaje) { this.error = mensaje; }, reportValidity() {} });
    const archivo = {};
    const datos = { value: '' };
    const dibujo = {};
    const bloqueArchivo = {};
    const limpiar = elemento();
    const form = elemento();
    const mapa = { canvas, '[data-firma-modo]': modo, 'input[type="file"]': archivo,
        'input[name="firma_dibujada"]': datos, '[data-firma-dibujo]': dibujo,
        '[data-firma-archivo]': bloqueArchivo, '[data-firma-limpiar]': limpiar };
    iniciarFirmaDirector({ querySelector: s => mapa[s], closest: () => form });
    return { canvas, modo, archivo, datos, dibujo, limpiar, form, puntos };
}

for (const pointerType of ['mouse', 'touch', 'pen']) {
    test(`captura ${pointerType} y conserva proporciones al escalar el canvas`, () => {
        const c = componente();
        c.modo.value = 'dibujar'; c.modo.listeners.change();
        const evento = { pointerId: 1, pointerType, button: 0, clientX: 110, clientY: 70, preventDefault() {} };
        c.canvas.listeners.pointerdown(evento);
        c.canvas.listeners.pointermove({ ...evento, clientX: 210 });
        c.canvas.listeners.pointerup(evento);
        assert.deepEqual(c.puntos, [[200, 100], [400, 100]]);
        assert.equal(c.datos.value, 'data:image/png;base64,trazos');
        assert.equal(c.archivo.disabled, true);
        assert.equal(c.datos.disabled, false);
    });
}

test('limpiar elimina el dibujo y el contenido que se enviaría', () => {
    const c = componente(); c.datos.value = 'firma'; c.limpiar.listeners.click();
    assert.equal(c.datos.value, ''); assert.deepEqual(c.puntos, ['limpio']);
});

test('cada modo habilita una sola fuente y conserva el dibujo al cambiar', () => {
    const c = componente();
    assert.equal(c.datos.disabled, true); assert.equal(c.archivo.disabled, false);
    c.modo.value = 'dibujar'; c.modo.listeners.change(); c.datos.value = 'firma';
    c.modo.value = 'archivo'; c.modo.listeners.change();
    assert.equal(c.datos.disabled, true); assert.equal(c.archivo.disabled, false);
    assert.equal(c.datos.value, 'firma');
});

test('dibujo vacío bloquea el envío; archivo opcional no lo bloquea', () => {
    const c = componente(); let bloqueos = 0;
    c.modo.value = 'dibujar'; c.modo.listeners.change();
    c.form.listeners.submit({ preventDefault() { bloqueos++; } });
    assert.equal(bloqueos, 1); assert.ok(c.modo.error);
    c.modo.value = 'archivo'; c.modo.listeners.change();
    c.form.listeners.submit({ preventDefault() { bloqueos++; } });
    assert.equal(bloqueos, 1); assert.equal(c.modo.error, '');
});

test('registro y actualización conservan firmas independientes', () => {
    const registro = componente(); const actualizacion = componente();
    registro.datos.value = 'firma registro'; actualizacion.limpiar.listeners.click();
    assert.equal(registro.datos.value, 'firma registro');
    assert.equal(actualizacion.datos.value, '');
});
