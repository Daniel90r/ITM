import {formatearValorDui} from './dui.js';

const mensaje = 'El DUI está incompleto. Ingresa 9 dígitos con el formato 00000000-0.';

export function actualizarDuiDirector(input, mostrarError = false) {
    const posicion = input.selectionStart ?? input.value.length;
    const antes = input.value.slice(0, posicion).replace(/[^0-9]/g, '').length;
    input.value = formatearValorDui(input.value);
    let cursor = 0, contados = 0;
    while (cursor < input.value.length && contados < antes) {
        if (/[0-9]/.test(input.value[cursor])) contados++;
        cursor++;
    }
    if (input.value[cursor] === '-') cursor++;
    input.setSelectionRange(cursor, cursor);
    const incompleto = input.value !== '' && !/^[0-9]{8}-[0-9]$/.test(input.value);
    input.setCustomValidity(incompleto ? mensaje : '');
    const error = document.getElementById(input.dataset.duiError);
    if (error) {
        error.hidden = !incompleto || !mostrarError;
        error.textContent = incompleto ? mensaje : '';
    }
    input.setAttribute('aria-invalid', String(incompleto && mostrarError));
}

document.addEventListener('input', event => {
    if (event.target.matches('[data-director-dui]')) actualizarDuiDirector(event.target);
});
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-director-dui]').forEach(input => actualizarDuiDirector(input));
});
for (const tipo of ['blur', 'invalid']) {
    document.addEventListener(tipo, event => {
        if (event.target.matches('[data-director-dui]')) actualizarDuiDirector(event.target, true);
    }, true);
}
document.addEventListener('beforeinput', event => {
    const input = event.target;
    if (!input.matches('[data-director-dui]')) return;
    const pos = input.selectionStart;
    // Borrar justo detrás del guion debe borrar el dígito anterior, sin atraparlo.
    if (event.inputType === 'deleteContentBackward' && pos === input.selectionEnd && pos === 9 && input.value[8] === '-') {
        event.preventDefault();
        input.value = input.value.slice(0, 7) + input.value.slice(9);
        input.setSelectionRange(7, 7);
        actualizarDuiDirector(input);
    }
    if (event.inputType === 'deleteContentForward' && pos === input.selectionEnd && pos === 8 && input.value[8] === '-') {
        event.preventDefault();
        input.value = input.value.slice(0, 8) + input.value.slice(10);
        input.setSelectionRange(8, 8);
        actualizarDuiDirector(input);
    }
});
