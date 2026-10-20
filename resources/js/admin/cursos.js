import './horarios-curso.js';

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
