export function iniciarFirmaDirector(contenedor) {
    const canvas = contenedor.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const modo = contenedor.querySelector('[data-firma-modo]');
    const archivo = contenedor.querySelector('input[type="file"]');
    const datos = contenedor.querySelector('input[name="firma_dibujada"]');
    let puntero = null;
    ctx.strokeStyle = '#111827';
    ctx.fillStyle = '#111827';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const posicion = evento => {
        const rect = canvas.getBoundingClientRect();
        return [(evento.clientX - rect.left) * canvas.width / rect.width,
            (evento.clientY - rect.top) * canvas.height / rect.height];
    };
    const cambiarModo = () => {
        const dibujar = modo.value === 'dibujar';
        contenedor.querySelector('[data-firma-dibujo]').hidden = !dibujar;
        contenedor.querySelector('[data-firma-archivo]').hidden = dibujar;
        archivo.disabled = dibujar;
        datos.disabled = !dibujar;
        modo.setCustomValidity('');
    };
    canvas.addEventListener('pointerdown', evento => {
        if (puntero !== null || (evento.pointerType === 'mouse' && evento.button !== 0)) return;
        evento.preventDefault();
        puntero = evento.pointerId;
        canvas.setPointerCapture(puntero);
        const [x, y] = posicion(evento);
        ctx.beginPath();
        ctx.arc(x, y, 1.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(x, y);
        modo.setCustomValidity('');
    });
    canvas.addEventListener('pointermove', evento => {
        if (evento.pointerId !== puntero) return;
        evento.preventDefault();
        ctx.lineTo(...posicion(evento));
        ctx.stroke();
    });
    const terminar = evento => {
        if (evento.pointerId !== puntero) return;
        datos.value = canvas.toDataURL('image/png');
        puntero = null;
    };
    canvas.addEventListener('pointerup', terminar);
    canvas.addEventListener('pointercancel', terminar);
    canvas.addEventListener('lostpointercapture', terminar);
    contenedor.querySelector('[data-firma-limpiar]').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        datos.value = '';
        puntero = null;
        modo.setCustomValidity('');
    });
    modo.addEventListener('change', cambiarModo);
    contenedor.closest('form').addEventListener('submit', evento => {
        if (puntero !== null) datos.value = canvas.toDataURL('image/png');
        if (modo.value === 'dibujar' && !datos.value) {
            evento.preventDefault();
            modo.setCustomValidity('Dibuja tu firma o elige subir una imagen.');
            modo.reportValidity();
        }
    });
    cambiarModo();
}

document.querySelectorAll('[data-firma-director]').forEach(iniciarFirmaDirector);
