import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {formatearValorDui} from '../../resources/js/utils/dui.js';

const listeners = {}, error = {hidden: true, textContent: ''};
const ctx = {formatearValorDui, document: {getElementById: () => error, addEventListener: (tipo, fn) => listeners[tipo] = fn}};
vm.createContext(ctx);
vm.runInContext(readFileSync(new URL('../../resources/js/utils/directorDui.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function'), ctx);
function input(value, pos = value.length) {
    return {value, selectionStart: pos, selectionEnd: pos, dataset: {duiError: 'error'},
        matches: () => true, setSelectionRange(a,b) {this.selectionStart=a;this.selectionEnd=b;},
        setCustomValidity(m) {this.message=m;}, setAttribute(k,v) {this[k]=v;}};
}

test('formatea pegado sin guion y con guion; limita a nueve dígitos', () => {
    for (const raw of ['232423432', '23242343-2', '23abc24234322222']) {
        const el = input(raw); ctx.actualizarDuiDirector(el);
        assert.equal(el.value, '23242343-2'); assert.equal(el.message, '');
    }
});
test('escritura progresiva y validación de DUI incompleto', () => {
    const el = input('');
    for (const digit of '232423432') {
        el.value += digit; el.selectionStart = el.value.length;
        listeners.input({target: el});
    }
    assert.equal(el.value, '23242343-2');
    el.value = '232'; el.selectionStart = 3; listeners.blur({target: el});
    assert.match(el.message, /incompleto/); assert.equal(error.hidden, false);
});
test('permite borrar todo y mantiene el DUI opcional', () => {
    const el = input('23242343-2');
    while (el.value.length) {el.value = el.value.slice(0,-1);el.selectionStart=el.value.length;listeners.input({target:el});}
    assert.equal(el.value, ''); assert.equal(el.message, ''); assert.equal(error.hidden, true);
});
test('corregir en medio conserva el cursor', () => {
    const el = input('23942343-2', 3); listeners.input({target: el});
    assert.equal(el.value, '23942343-2'); assert.equal(el.selectionStart, 3);
});
test('retroceso después del guion borra el dígito previo', () => {
    const el = input('23242343-2', 9); let prevented = false;
    listeners.beforeinput({target: el, inputType: 'deleteContentBackward', preventDefault() {prevented=true;}});
    assert.equal(prevented, true); assert.equal(el.value, '23242342'); assert.equal(el.selectionStart, 7);
});
test('suprimir delante del guion borra el dígito siguiente', () => {
    const el = input('23242343-2', 8); let prevented = false;
    listeners.beforeinput({target: el, inputType: 'deleteContentForward', preventDefault() {prevented=true;}});
    assert.equal(prevented, true); assert.equal(el.value, '23242343'); assert.equal(el.selectionStart, 8);
});
