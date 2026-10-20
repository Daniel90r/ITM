function initFirma(canvasId, dataId) {
    const canvas  = document.getElementById(canvasId);
    const ctx     = canvas.getContext('2d');
    const input   = document.getElementById(dataId);
    let dibujando = false;

    ctx.strokeStyle = '#111827';
    ctx.lineWidth   = 2;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';

    function getPosicion(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        if (e.touches) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top)  * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top)  * scaleY
        };
    }

    function iniciar(e) {
        e.preventDefault();
        dibujando = true;
        const pos = getPosicion(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        canvas.style.borderColor = '#f59e0b';
    }

    function dibujar(e) {
        if (!dibujando) return;
        e.preventDefault();
        const pos = getPosicion(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        input.value = canvas.toDataURL();
    }

    function terminar(e) {
        if (!dibujando) return;
        dibujando = false;
        input.value = canvas.toDataURL();
    }

    canvas.addEventListener('mousedown',  iniciar);
    canvas.addEventListener('mousemove',  dibujar);
    canvas.addEventListener('mouseup',    terminar);
    canvas.addEventListener('mouseleave', terminar);
    canvas.addEventListener('touchstart', iniciar,   { passive: false });
    canvas.addEventListener('touchmove',  dibujar,   { passive: false });
    canvas.addEventListener('touchend',   terminar);
    canvas.addEventListener('touchcancel', terminar);
}

function limpiarFirma(canvasId, dataId) {
    const canvas = document.getElementById(canvasId);
    const input = document.getElementById(dataId);


    if (!canvas || !input) return;


    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    input.value = '';
    canvas.style.borderColor = '#e5e7eb';
}

function enviarSolicitud() {
    const formulario = document.getElementById('formInscripcion');
    if (!formulario || formulario.dataset.encargadoBloqueado === '1') return;

    const firmaAlumnoInput = document.getElementById('firmaAlumnoData');
    const firmaEncargadoInput = document.getElementById('firmaEncargadoData');
    const curso = document.querySelector('.curso-radio:checked');


    if (!firmaAlumnoInput || !firmaAlumnoInput.value) {
        alert('Por favor dibuja tu firma.');
        return;
    }


    if (firmaEncargadoInput && !firmaEncargadoInput.value) {
        alert('Por favor dibuja la firma del encargado.');
        return;
    }


    if (!curso) {
        alert('Por favor selecciona una sección.');
        return;
    }


    if (formulario.reportValidity()) formulario.submit();
}

window.limpiarFirma = limpiarFirma;
window.enviarSolicitud = enviarSolicitud;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.curso-radio').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.curso-card').forEach(c => {
                c.style.borderColor = '#e5e7eb';
                c.style.background = '#ffffff';
            });

            radio.nextElementSibling.style.borderColor = '#f59e0b';
            radio.nextElementSibling.style.background = '#fefce8';
        });
    });

    if (document.getElementById('firmaAlumno')) {
        initFirma('firmaAlumno', 'firmaAlumnoData');
    }

    if (document.getElementById('firmaEncargado')) {
        initFirma('firmaEncargado', 'firmaEncargadoData');
    }
});
