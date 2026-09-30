// MÁSCARAS DE ENTRADA: Teléfono (reutilizable vía delegación)

const formatearTelefono = (valor) => {
    const numeros = valor.replace(/\D/g, '').slice(0, 8);

    if (numeros.length <= 4) {
        return numeros;
    }

    return `${numeros.slice(0, 4)}-${numeros.slice(4)}`;
};

const esClavePermitida = (e) => {
    const esCtrl = e.ctrlKey || e.metaKey;
    if (esCtrl) return true;

    const teclasPermitidas = [
        'Backspace',
        'Delete',
        'Tab',
        'ArrowLeft',
        'ArrowRight',
        'Home',
        'End',
        'Enter',
    ];

    if (teclasPermitidas.includes(e.key)) return true;

    if (e.shiftKey && ['Tab'].includes(e.key)) return true;

    if (e.key.length === 1 && /^[0-9]$/.test(e.key)) return true;

    return false;
};

document.addEventListener('keydown', (e) => {
    const input = e.target;

    if (!input.matches('[data-phone-mask]')) return;

    if (!esClavePermitida(e)) {
        e.preventDefault();
    }
});

document.addEventListener('input', (e) => {
    const input = e.target;

    if (!input.matches('[data-phone-mask]')) return;

    const cursorStart = input.selectionStart;
    const cursorEnd   = input.selectionEnd;

    const valorAnterior = input.value;
    const nuevoValor    = formatearTelefono(input.value);

    if (nuevoValor !== valorAnterior) {
        const ajuste = valorAnterior.length - nuevoValor.length;
        input.value = nuevoValor;

        let nuevaPosicion = cursorStart + ajuste;

        if (valorAnterior.length > nuevoValor.length) {
            nuevaPosicion = Math.min(nuevaPosicion, nuevoValor.length);
        } else if (valorAnterior.length < nuevoValor.length) {
            if (cursorStart >= 4 && !valorAnterior.includes('-')) {
                nuevaPosicion = cursorStart + 1;
            }
        }

        nuevaPosicion = Math.max(0, Math.min(nuevaPosicion, nuevoValor.length));
        input.setSelectionRange(nuevaPosicion, nuevaPosicion);
    }
});
