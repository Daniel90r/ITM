import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../resources/js/filtros-autocompletado.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
class Node {
    constructor() { this.children = []; this.attrs = {}; this.dataset = {}; this.style = {}; this.listeners = {}; this.value = ''; this.labels = [1]; this.classList = {add() {}}; }
    setAttribute(k, v) { this.attrs[k] = String(v); }
    getAttribute(k) { return this.attrs[k]; }
    hasAttribute(k) { return k in this.attrs; }
    removeAttribute(k) { delete this.attrs[k]; }
    addEventListener(k, fn) { (this.listeners[k] ||= []).push(fn); }
    dispatchEvent(event) {
        event.target ||= this;
        if (this.hasAttribute('on' + event.type)) this.legacyHandler?.(event);
        for (const fn of this.listeners[event.type] || []) fn(event);
    }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    contains(node) { return this === node || this.children.some(child => child.contains?.(node)); }
    matches() { return false; }
    scrollIntoView() {}
    getBoundingClientRect() { return {left: 20, right: 300, top: 100, bottom: 144, width: 280}; }
}
function setup({legacy = false, submit = true} = {}) {
    const input = new Node(), body = new Node(), document = new Node(), timers = new Map(), calls = [], submissions = [];
    const anio = new Node(), mes = new Node(), selected = new Node(); anio.value = '2025'; mes.value = '10';
    const form = new Node(); form.elements = Object.assign([input, anio, mes, selected], {anio, mes, alumno_id: selected}); input.form = form;
    form.requestSubmit = () => { form.dispatchEvent({type: 'submit'}); submissions.push({q: input.value, anio: anio.value, mes: mes.value, alumno_id: selected.value}); };
    document.body = body; document.readyState = 'complete'; document.querySelector = selector => selector === '#input' ? input : null;
    document.createElement = () => new Node();
    if (legacy) {
        input.setAttribute('oninput', 'filtrarMensualidades()');
        input.legacyHandler = () => timers.set(++timerId, () => form.requestSubmit());
    }
    const window = new Node(); window.itmFiltrosAutocompletado = [{selector: '#input', url: 'http://localhost/sugerencias', submit, selected: 'alumno_id'}];
    let timerId = 0;
    class FormData { constructor() { this.values = [['q', input.value], ['anio', anio.value], ['mes', mes.value], ['alumno_id', selected.value]]; } [Symbol.iterator]() { return this.values[Symbol.iterator](); } }
    class Event { constructor(type, extra = {}) { this.type = type; Object.assign(this, extra); } preventDefault() { this.prevented = true; } stopPropagation() {} }
    vm.runInNewContext(source, {document, window, FormData, Event, URL, URLSearchParams, AbortController,
        location: {href: 'http://localhost/pagos'}, innerHeight: 900, innerWidth: 375,
        setTimeout(fn) { timers.set(++timerId, fn); return timerId; }, clearTimeout(id) { timers.delete(id); },
        fetch(url, options) { return new Promise(resolve => calls.push({url, options, resolve})); }});
    const list = body.children[0], status = body.children[1];
    return {input, anio, mes, selected, list, status, calls, submissions, document, window,
        type(value) { input.value = value; input.dispatchEvent(new Event('input')); },
        key(key) { const e = new Event('keydown', {key}); input.dispatchEvent(e); return e; },
        run() { const queued = [...timers.values()]; timers.clear(); queued.forEach(fn => fn()); },
        async respond(index, alumnos) { calls[index].resolve({ok: true, json: async () => ({alumnos})}); await flush(); },
    };
}
const ana = {id: 7, nombre: 'Ana Isabel', codigo: 'ITM-007'};

test('mínimo de dos caracteres, debounce y parámetros activos sin identificador previo', () => {
    const x = setup(); x.type('A'); x.run(); assert.equal(x.calls.length, 0);
    x.type('An'); x.type('Ana'); x.run(); assert.equal(x.calls.length, 1);
    const params = x.calls[0].url.searchParams;
    assert.equal(params.get('q'), 'Ana'); assert.equal(params.get('anio'), '2025'); assert.equal(params.get('mes'), '10');
    assert.equal(params.has('alumno_id'), false);
});
test('flechas y Enter seleccionan sin perder filtros; editar limpia el identificador', async () => {
    const x = setup(); x.type('An'); x.run(); await x.respond(0, [ana]);
    assert.equal(x.input.getAttribute('aria-expanded'), 'true');
    x.key('ArrowUp'); assert.equal(x.list.children[0].getAttribute('aria-selected'), 'true');
    assert.equal(x.key('Enter').prevented, true);
    assert.deepEqual(x.submissions, [{q: 'ITM-007', alumno_id: 7, anio: '2025', mes: '10'}]);
    x.type('Lu'); assert.equal(x.selected.value, '');
});
test('clic selecciona; Escape y clic exterior cierran el desplegable', async () => {
    const x = setup(); x.type('An'); x.run(); await x.respond(0, [ana]);
    x.list.children[0].dispatchEvent({type: 'click'}); assert.equal(x.submissions.length, 1);
    x.type('An'); x.run(); await x.respond(1, [ana]); x.key('Escape'); assert.equal(x.list.hidden, true);
    x.type('An'); x.run(); await x.respond(2, [ana]); x.document.dispatchEvent({type: 'click', target: new Node()}); assert.equal(x.list.hidden, true);
});
test('descarta respuestas anteriores aunque el transporte no respete AbortController', async () => {
    const x = setup(); x.type('An'); x.run(); x.type('Lu'); x.run();
    assert.equal(x.calls[0].options.signal.aborted, true);
    await x.respond(1, [{id: 8, nombre: 'Luis', codigo: 'ITM-008'}]); await x.respond(0, [ana]);
    assert.equal(x.list.children[0].children[1].children[0].textContent, 'Luis');
});
test('mensajes vacíos, límite de ocho y año explícitamente vacío', async () => {
    const x = setup(); x.anio.value = ''; x.type('xx'); x.run(); await x.respond(0, []);
    assert.equal(x.calls[0].url.searchParams.get('anio'), '');
    assert.equal(x.status.textContent, 'No se encontraron coincidencias');
    x.type('An'); x.run(); await x.respond(1, Array(12).fill(ana)); assert.equal(x.list.children.length, 8);
});
test('el manejador heredado no envía al escribir ni al seleccionar y no duplica el envío', async () => {
    const x = setup({legacy: true});
    x.type('m'); x.run(); assert.equal(x.submissions.length, 0); assert.equal(x.calls.length, 0);
    x.type('ma'); x.run(); await x.respond(0, [ana]); assert.equal(x.submissions.length, 0);
    x.list.children[0].dispatchEvent({type: 'click'});
    x.list.children[0].dispatchEvent({type: 'click'}); x.run();
    assert.equal(x.submissions.length, 1);
});
test('los filtros locales conservan sus manejadores existentes', () => {
    const x = setup({legacy: true, submit: false});
    assert.equal(x.input.hasAttribute('oninput'), true);
});
test('escribir no envía; el envío manual del formulario sigue disponible', () => {
    const x = setup({legacy: true}); x.type('m'); x.run();
    assert.equal(x.submissions.length, 0);
    x.input.form.requestSubmit();
    assert.deepEqual(x.submissions, [{q: 'm', alumno_id: '', anio: '2025', mes: '10'}]);
});
