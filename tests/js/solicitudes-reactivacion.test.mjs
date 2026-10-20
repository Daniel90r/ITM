import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';

const source = readFileSync(new URL('../../resources/js/admin/solicitudes.js', import.meta.url), 'utf8');

function entorno(reabrir = '') {
    let inicializaciones = 0;
    let listo;
    const formulario = { dataset: { confirmInicializado: 'true', confirmLocked: 'true' } };
    const cuerpo = { innerHTML: '', querySelectorAll: () => [formulario] };
    const modal = { dataset: { reabrir }, style: {}, addEventListener() {} };
    const elementos = { 'detalle-7': { innerHTML: '<form>Reactivar</form>' }, cuerpoDetalle: cuerpo, modalDetalle: modal };
    const document = {
        body: { style: {} },
        getElementById: id => elementos[id],
        addEventListener(evento, callback) { if (evento === 'DOMContentLoaded') listo = callback; },
    };
    const window = {
        initConfirmaciones() {
            assert.equal(formulario.dataset.confirmInicializado, undefined);
            assert.equal(formulario.dataset.confirmLocked, undefined);
            inicializaciones++;
        },
    };
    runInNewContext(source, { document, window });
    return { window, document, cuerpo, modal, listo: () => listo(), inicializaciones: () => inicializaciones };
}

test('abrir detalle reinicializa la confirmación clonada sin enviar el formulario', () => {
    const e = entorno();
    e.window.abrirDetalle(7);
    assert.equal(e.cuerpo.innerHTML, '<form>Reactivar</form>');
    assert.equal(e.inicializaciones(), 1);
    assert.equal(e.modal.style.display, 'flex');
    e.window.cerrarDetalle();
    assert.equal(e.modal.style.display, 'none');
    assert.equal(e.document.body.style.overflow, '');
});

test('un error de validación reabre el detalle indicado sin enviar la reactivación', () => {
    const e = entorno('7');
    e.listo();
    assert.equal(e.modal.style.display, 'flex');
    assert.equal(e.inicializaciones(), 1);
});

test('cargar normalmente el listado no abre ni ejecuta una reactivación', () => {
    const e = entorno();
    e.listo();
    assert.equal(e.modal.style.display, undefined);
    assert.equal(e.inicializaciones(), 0);
});
