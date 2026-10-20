import assert from 'node:assert/strict';
import { test } from 'node:test';
import { initAsistencias } from '../../resources/js/admin/asistencias.js';

const esperar = () => new Promise(resolve => setImmediate(resolve));

function entorno(responder = async () => ({ ok: true, json: async () => ({ html: '<table>Detalle</table>' }) })) {
    let click;
    let listeners = 0;
    const peticiones = [];
    const panel = { dataset: {}, attributes: {}, innerHTML: '', setAttribute(k, v) { this.attributes[k] = v; }, replaceChildren(...children) { this.children = children; } };
    const fila = { hidden: true, querySelector: () => panel };
    const boton = { dataset: { url: '/admin/asistencias/alumnos/3/detalle?curso_id=4&q=Ana' }, attributes: { 'aria-expanded': 'false', 'aria-controls': 'asistencias-alumno-3' },
        getAttribute(k) { return this.attributes[k]; }, setAttribute(k, v) { this.attributes[k] = v; } };
    const root = { dataset: {}, addEventListener(_, callback) { click = callback; listeners++; } };
    globalThis.document = { getElementById: () => fila, createElement: () => ({ dataset: {}, setAttribute() {} }) };
    globalThis.fetch = async (url, options) => { peticiones.push({ url, options }); return responder(url); };
    initAsistencias(root);
    return { root, panel, fila, boton, peticiones, listeners: () => listeners,
        toggle() { click({ target: { closest: selector => selector === '[data-ver-asistencias]' ? boton : null } }); },
        paginar(url) { let prevented = false; click({ preventDefault() { prevented = true; }, target: { closest: selector => selector === '.detalle-paginacion a' ? { href: url, closest: () => panel } : null } }); return prevented; },
        reintentar() { click({ target: { closest: selector => selector === '[data-detalle-reintentar]' ? { closest: () => panel } : null } }); },
    };
}

test('no carga nada hasta expandir y conserva filtros en una petición de lectura', async () => {
    const e = entorno();
    assert.equal(e.peticiones.length, 0);
    e.toggle();
    await esperar();
    assert.equal(e.fila.hidden, false);
    assert.equal(e.boton.attributes['aria-expanded'], 'true');
    assert.equal(e.boton.textContent, 'Ocultar asistencias');
    assert.equal(e.peticiones[0].url, e.boton.dataset.url);
    assert.equal(e.peticiones[0].options.credentials, 'same-origin');
    assert.equal(e.peticiones[0].options.headers.Accept, 'application/json');
    assert.equal(e.peticiones[0].options.method, undefined); // GET por defecto.
    assert.equal(e.panel.innerHTML, '<table>Detalle</table>');
    assert.equal(e.panel.attributes['aria-busy'], 'false');
});

test('contrae y vuelve a expandir sin recargar la página ni repetir la consulta', async () => {
    const e = entorno(); e.toggle(); await esperar();
    e.toggle();
    assert.equal(e.fila.hidden, true);
    assert.equal(e.boton.attributes['aria-expanded'], 'false');
    e.toggle(); await esperar();
    assert.equal(e.peticiones.length, 1);
    assert.equal(e.fila.hidden, false);
});

test('la paginación reemplaza solo el detalle y utiliza la URL filtrada del servidor', async () => {
    const e = entorno(); e.toggle(); await esperar();
    const url = `${e.boton.dataset.url}&detalle_page=2`;
    assert.equal(e.paginar(url), true);
    await esperar();
    assert.equal(e.peticiones.length, 2);
    assert.equal(e.peticiones[1].url, url);
    assert.equal(e.fila.hidden, false);
});

test('muestra un fallo recuperable y reintenta el mismo detalle', async () => {
    let veces = 0;
    const e = entorno(async () => { if (veces++ === 0) throw Error('red'); return { ok: true, json: async () => ({ html: 'Recuperado' }) }; });
    e.toggle(); await esperar();
    assert.match(e.panel.children[0].textContent, /No fue posible/);
    assert.equal(e.panel.dataset.cargado, 'false');
    e.reintentar(); await esperar();
    assert.equal(e.panel.innerHTML, 'Recuperado');
    assert.equal(e.peticiones[0].url, e.peticiones[1].url);
});

test('no inserta respuestas no autorizadas ni pantallas de inicio de sesión', async () => {
    const e = entorno(async () => ({ ok: false, status: 403 }));
    e.toggle(); await esperar();
    assert.match(e.panel.children[0].textContent, /no tienes permiso/);
    assert.equal(e.panel.dataset.cargado, 'false');
});

test('no duplica consultas mientras está cargando ni registra listeners duplicados', async () => {
    let resolver;
    const e = entorno(() => new Promise(resolve => { resolver = resolve; }));
    initAsistencias(e.root);
    assert.equal(e.listeners(), 1);
    e.toggle(); e.toggle(); e.toggle();
    assert.equal(e.peticiones.length, 1);
    resolver({ ok: true, json: async () => ({ html: 'Detalle' }) });
    await esperar();
    assert.equal(e.panel.dataset.cargado, 'true');
});
