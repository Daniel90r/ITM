import assert from 'node:assert/strict';
import { test } from 'node:test';
import { initMensualidades, seleccionUnica, prepararCobroLote } from '../../resources/js/admin/mensualidades.js';

const esperar = () => new Promise(resolve => setImmediate(resolve));
const checkbox = (value, monto, visible = true) => ({ value, checked: true, dataset: { monto, curso: '4' }, closest: () => ({ offsetParent: visible ? {} : null }) });

function entorno(responder = async () => ({ ok: true, json: async () => ({ html: '<ol>Meses</ol>' }) })) {
    const listeners = {};
    const peticiones = [];
    const checks = [];
    let confirmaciones = 0;
    let envios = 0;
    const panel = { dataset: {}, innerHTML: '', querySelectorAll: () => checks, setAttribute() {}, replaceChildren(...children) { this.children = children; } };
    const detalle = { hidden: true, querySelector: () => panel };
    const boton = { dataset: { url: '/admin/mensualidades/cursos/4/alumnos/3?anio=2026&mes=Enero' }, attributes: { 'aria-expanded': 'false', 'aria-controls': 'detalle-4-3' }, getAttribute(k) { return this.attributes[k]; }, setAttribute(k, v) { this.attributes[k] = v; } };
    const barra = { style: {}, submit() { envios++; } };
    const elementos = { 'detalle-4-3': detalle, formLote: barra, loteCantidad: {}, loteTotal: {} };
    const root = { dataset: {}, addEventListener(event, callback) { assert.equal(listeners[event], undefined); listeners[event] = callback; } };
    globalThis.document = {
        getElementById: id => elementos[id],
        querySelectorAll: selector => selector === '.chk-curso' ? [] : (selector.includes(':checked') ? checks.filter(c => c.checked) : checks),
        createElement: () => ({ dataset: {}, setAttribute() {} }),
    };
    globalThis.window = { initConfirmaciones() { confirmaciones++; }, mostrarNotificacion() {} };
    globalThis.fetch = async (url, options) => { peticiones.push({ url, options }); return responder(); };
    initMensualidades(root);
    return { root, panel, detalle, boton, checks, peticiones, barra, elementos, confirmaciones: () => confirmaciones, envios: () => envios,
        toggle() { listeners.click({ target: { closest: selector => selector === '[data-ver-mensualidades]' ? boton : null } }); },
        paginar(url) { let prevented = false; listeners.click({ preventDefault() { prevented = true; }, target: { closest: selector => selector === '[data-mensualidad-paginacion] a' ? { href: url, closest: () => panel } : null } }); return prevented; },
        reintentar() { listeners.click({ target: { closest: selector => selector === '[data-mensualidades-reintentar]' ? { closest: () => panel } : null } }); },
    };
}

test('solo carga al expandir, conserva filtros y activa las confirmaciones de los pagos', async () => {
    const e = entorno(); assert.equal(e.peticiones.length, 0);
    e.toggle(); await esperar();
    assert.equal(e.detalle.hidden, false);
    assert.equal(e.boton.attributes['aria-expanded'], 'true');
    assert.equal(e.peticiones[0].url, e.boton.dataset.url);
    assert.equal(e.peticiones[0].options.method, undefined);
    assert.equal(e.peticiones[0].options.credentials, 'same-origin');
    assert.equal(e.confirmaciones(), 1);
    assert.equal(e.envios(), 0);
});

test('cerrar desmarca los pagos ocultos y reabrir utiliza la página cargada', async () => {
    const e = entorno(); e.toggle(); await esperar();
    const check = checkbox('1', '25'); e.checks.push(check);
    e.toggle();
    assert.equal(check.checked, false); assert.equal(e.detalle.hidden, true);
    assert.equal(e.barra.style.display, 'none');
    e.toggle(); await esperar(); assert.equal(e.peticiones.length, 1);
});

test('paginar desmarca la página anterior y solo reemplaza sus detalles', async () => {
    const e = entorno(); e.toggle(); await esperar();
    const check = checkbox('1', '25'); e.checks.push(check);
    const url = e.boton.dataset.url + '&mensualidad_page=2';
    assert.equal(e.paginar(url), true); assert.equal(check.checked, false);
    await esperar(); assert.equal(e.peticiones[1].url, url); assert.equal(e.envios(), 0);
});

test('maneja fallos de carga y permite reintentar sin enviar pagos', async () => {
    let intentos = 0;
    const e = entorno(async () => { if (!intentos++) throw Error('red'); return { ok: true, json: async () => ({ html: 'Recuperado' }) }; });
    e.toggle(); await esperar(); assert.match(e.panel.children[0].textContent, /No fue posible/);
    e.reintentar(); await esperar(); assert.equal(e.panel.innerHTML, 'Recuperado'); assert.equal(e.envios(), 0);
});

test('una sesión no autorizada no se inserta como detalle', async () => {
    const e = entorno(async () => ({ ok: false, status: 403 }));
    e.toggle(); await esperar(); assert.match(e.panel.children[0].textContent, /no tienes permiso/);
});

test('deduplica una misma mensualidad visible en dos cursos y excluye las ocultas', () => {
    const a = checkbox('1', '25'); const b = checkbox('1', '25'); const c = checkbox('2', '40'); const oculta = checkbox('3', '100', false);
    assert.deepEqual(seleccionUnica([a,b,c,oculta]).map(c => c.value), ['1','2']);
});

test('el cobro en lote exige confirmación y envía cada ID visible una sola vez', async () => {
    const e = entorno(); e.checks.push(checkbox('1', '25.50'), checkbox('1', '25.50'), checkbox('2', '40'), checkbox('3', '100', false));
    let mensaje;
    window.mostrarConfirmacion = async options => { mensaje = options.mensaje; return { isConfirmed: false }; };
    prepararCobroLote({ preventDefault() {} }); await esperar();
    assert.match(mensaje, /2 pago\(s\) por \$65.50/); assert.equal(e.envios(), 0);
    window.mostrarConfirmacion = async () => ({ isConfirmed: true });
    prepararCobroLote({ preventDefault() {} }); await esperar();
    assert.equal(e.envios(), 1);
    assert.deepEqual(e.checks.filter(c => c.checked).map(c => c.value), ['1','2']);
});

test('inicializar dos veces no duplica controles ni cargas', async () => {
    const e = entorno(); initMensualidades(e.root); e.toggle(); await esperar();
    assert.equal(e.peticiones.length, 1);
});
