// ═══════════════════════════════════════════════════════════════════════════════════════════
// FILTROS Y BÚSQUEDA
// ═══════════════════════════════════════════════════════════════════════════════════════════
let filtroEstadoActual = 'todos';

function setFiltroEstado(estado, boton) {
    filtroEstadoActual = estado;
    document.querySelectorAll('.filtro-estado').forEach(b => b.classList.remove('activo'));
    boton.classList.add('activo');
    filtrarSolicitudes();
}

function filtrarSolicitudes() {
    const q = (document.getElementById('buscarSolicitud')?.value || '').toLowerCase().trim();
    const filas = document.querySelectorAll('.fila-solicitud');
    let visibles = 0;

    filas.forEach(fila => {
        const okEstado = filtroEstadoActual === 'todos' || fila.dataset.estado === filtroEstadoActual;
        const okBusqueda = !q || (fila.dataset.busqueda || '').includes(q);
        const mostrar = okEstado && okBusqueda;
        fila.style.display = mostrar ? '' : 'none';
        if (mostrar) visibles++;
    });

    const sinResultados = document.getElementById('sinResultadosSolicitudes');
    if (sinResultados) {
        sinResultados.style.display = visibles === 0 ? 'block' : 'none';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════════════════
// MODAL DETALLE
// ═══════════════════════════════════════════════════════════════════════════════════════════
function abrirDetalle(id) {
    const origen = document.getElementById('detalle-' + id);
    const cuerpo = document.getElementById('cuerpoDetalle');
    if (!origen || !cuerpo) return;
    cuerpo.innerHTML = origen.innerHTML;
    document.getElementById('modalDetalle').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarDetalle() {
    document.getElementById('modalDetalle').style.display = 'none';
    document.body.style.overflow = '';
}

// ═══════════════════════════════════════════════════════════════════════════════════════════
// MODAL RECHAZO
// ═══════════════════════════════════════════════════════════════════════════════════════════
function abrirRechazo(id, nombre) {
    document.getElementById('textoRechazo').textContent = 'Estás rechazando la solicitud de ' + nombre + '. Debes indicar el motivo.';
    document.getElementById('formRechazo').action = '/admin/solicitudes/' + id + '/rechazar';
    document.getElementById('modalRechazo').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarRechazo() {
    document.getElementById('modalRechazo').style.display = 'none';
    document.body.style.overflow = '';
}

// Exponer funciones globalmente
window.setFiltroEstado = setFiltroEstado;
window.filtrarSolicitudes = filtrarSolicitudes;
window.abrirDetalle = abrirDetalle;
window.cerrarDetalle = cerrarDetalle;
window.abrirRechazo = abrirRechazo;
window.cerrarRechazo = cerrarRechazo;

document.addEventListener('DOMContentLoaded', function () {
    const modalRechazo = document.getElementById('modalRechazo');
    const modalDetalle = document.getElementById('modalDetalle');

    if (modalRechazo) {
        modalRechazo.addEventListener('click', function (e) {
            if (e.target === this) cerrarRechazo();
        });
    }
    if (modalDetalle) {
        modalDetalle.addEventListener('click', function (e) {
            if (e.target === this) cerrarDetalle();
        });
    }
});
