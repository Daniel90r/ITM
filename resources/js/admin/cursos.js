import './horarios-curso.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-finalizar-curso').forEach(form => {
        if (form.dataset.finalizarInicializado) return;
        form.dataset.finalizarInicializado = 'true';
        const button = form.querySelector('button[type="submit"]');
        const error = form.querySelector('[data-finalizar-error]');
        const label = button.textContent;
        const reset = () => {
            delete form.dataset.finalizarBusy;
            form.removeAttribute('aria-busy');
            button.disabled = false;
            button.textContent = label;
        };
        window.addEventListener('pageshow', reset);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.finalizarBusy || !form.reportValidity()) return;
            form.dataset.finalizarBusy = 'true';
            error.textContent = '';
            let enviado = false;
            try {
                if (typeof window.mostrarConfirmacion !== 'function') throw new Error('Confirmación no disponible');
                const result = await window.mostrarConfirmacion({
                    titulo: form.dataset.confirmTitulo,
                    mensaje: form.dataset.finalizarConfirm,
                    boton: form.dataset.confirmBoton,
                    tipo: 'peligro',
                    opciones: {focusCancel: true, returnFocus: true, allowOutsideClick: false},
                });
                if (!result.isConfirmed) return;
                button.disabled = true;
                button.textContent = 'Finalizando…';
                form.setAttribute('aria-busy', 'true');
                HTMLFormElement.prototype.submit.call(form);
                enviado = true;
            } catch {
                error.textContent = 'No se pudo enviar la solicitud. Inténtalo de nuevo; si el problema continúa, recarga la página.';
            } finally {
                if (!enviado) reset();
            }
        });
    });
});

function abrirModalCurso() {
    document.getElementById('modalNuevoCurso').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalCurso() {
    document.getElementById('modalNuevoCurso').style.display = 'none';
    document.body.style.overflow = '';
}

function filtrarCursos() {
    const texto    = document.getElementById('buscadorCurso').value.toLowerCase().trim();
    const turno    = document.getElementById('filtroTurno').value.toLowerCase();
    const cards    = document.querySelectorAll('.card-curso');
    let visibles   = 0;

    cards.forEach(card => {
        const nombre     = card.dataset.nombre;
        const turnoCard  = card.dataset.turno;
        const okNombre   = !texto || nombre.includes(texto);
        const okTurno    = !turno || turnoCard.includes(turno);

        if (okNombre && okTurno) {
            card.style.display = '';
            visibles++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('sinResultados').style.display = visibles === 0 ? 'block' : 'none';
}

function limpiarFiltrosCursos() {
    document.getElementById('buscadorCurso').value = '';
    document.getElementById('filtroTurno').value = '';
    document.getElementById('sinResultados').style.display = 'none';
    document.querySelectorAll('.card-curso').forEach(c => c.style.display = '');
}

window.abrirModalCurso = abrirModalCurso;
window.cerrarModalCurso = cerrarModalCurso;
window.filtrarCursos = filtrarCursos;
window.limpiarFiltrosCursos = limpiarFiltrosCursos;

document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('#modalNuevoCurso .horario-error')) abrirModalCurso();
    document.getElementById('modalNuevoCurso')?.addEventListener('click', function(e) {
        if (e.target === this) cerrarModalCurso();
    });
});
