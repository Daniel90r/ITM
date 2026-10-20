import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

test('menú: abrir, atrapar foco, Escape, fondo y cambio a escritorio', () => {
    const document = { activeElement: null, handlers: {}, addEventListener(type, cb) { this.handlers[type] = cb; } };
    const element = () => ({ handlers: {}, attributes: {}, inert: false,
        addEventListener(type, cb) { this.handlers[type] = cb; },
        setAttribute(name, value) { this.attributes[name] = value; },
        getClientRects() { return [{}]; }, focus() { document.activeElement = this; },
    });
    const close = element(), toggle = element(), link = element(), backdrop = element(), main = element(), menu = element();
    menu.querySelectorAll = () => [close, link];
    const elements = { '#portal-menu': menu, '.portal-menu-toggle': toggle, '.portal-menu-close': close, '.portal-menu-backdrop': backdrop, '.portal-main': main };
    const classes = new Set();
    const portal = { querySelector: key => elements[key], classList: { toggle(name, value) { value ? classes.add(name) : classes.delete(name); } } };
    document.querySelector = selector => selector === '.portal-alumno' ? portal : null;
    const mobile = { matches: true, addEventListener(type, cb) { this.change = cb; } };
    const source = readFileSync(new URL('../../resources/js/alumno/portal.js', import.meta.url), 'utf8').replace(/^import .*;\s*/m, '');
    vm.runInNewContext(source, { document, window: { matchMedia: () => mobile } });
    assert.equal(menu.inert, true);
    toggle.handlers.click();
    assert.equal(toggle.attributes['aria-expanded'], 'true');
    assert.equal(main.inert, true);
    assert.equal(document.activeElement, close);
    link.focus();
    let prevented = false;
    document.handlers.keydown({ key: 'Tab', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(document.activeElement, close);
    document.handlers.keydown({ key: 'Escape', preventDefault() {} });
    assert.equal(toggle.attributes['aria-expanded'], 'false');
    assert.equal(main.inert, false);
    assert.equal(document.activeElement, toggle);
    toggle.handlers.click(); backdrop.handlers.click();
    assert.equal(backdrop.hidden, true);
    toggle.handlers.click(); mobile.matches = false; mobile.change();
    assert.equal(menu.inert, false);
    assert.equal(main.inert, false);
    assert.equal(classes.has('portal-menu-open'), false);
});

test('firma táctil: escala coordenadas, conserva PNG al cancelar y permite limpiar', () => {
    const handlers = {}, operations = [];
    const ctx = { beginPath() {}, moveTo(x, y) { operations.push(['start', x, y]); }, lineTo(x, y) { operations.push(['line', x, y]); }, stroke() {}, clearRect() {} };
    const canvas = { width: 300, height: 120, style: {}, getContext: () => ctx,
        getBoundingClientRect: () => ({ left: 10, top: 20, width: 150, height: 60 }),
        toDataURL: () => 'data:image/png;base64,firma-ficticia',
        addEventListener(type, cb, options) { handlers[type] = { cb, options }; },
    };
    const input = { value: '' };
    const context = vm.createContext({ document: { getElementById: id => id === 'canvas' ? canvas : input, addEventListener() {} }, window: {} });
    vm.runInContext(readFileSync(new URL('../../resources/js/alumno/inscripcion.js', import.meta.url), 'utf8'), context);
    context.initFirma('canvas', 'input');
    let prevented = 0;
    const touch = (x, y) => ({ touches: [{ clientX: x, clientY: y }], preventDefault() { prevented++; } });
    handlers.touchstart.cb(touch(20, 30));
    handlers.touchmove.cb(touch(40, 45));
    handlers.touchcancel.cb({});
    handlers.touchmove.cb(touch(60, 50));
    assert.deepEqual(operations, [['start', 20, 20], ['line', 60, 50]]);
    assert.equal(prevented, 2);
    assert.equal(handlers.touchmove.options.passive, false);
    assert.equal(input.value, 'data:image/png;base64,firma-ficticia');
    context.window.limpiarFirma('canvas', 'input');
    assert.equal(input.value, '');
});
