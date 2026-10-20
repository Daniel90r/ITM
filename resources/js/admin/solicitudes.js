// Los filtros consultan todo el listado en el servidor, antes de paginar.
let temporizadorBusqueda;

function setFiltroEstado(estado) {
    clearTimeout(temporizadorBusqueda);
    const form = document.getElementById('filtrosSolicitudes');
    if (!form) return;
    document.getElementById('estadoSolicitud').value = estado;
    form.requestSubmit();
}

function filtrarSolicitudes() {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(() => {
        document.getElementById('filtrosSolicitudes')?.requestSubmit();
    }, 400);
}

// MODAL DETALLE
function abrirDetalle(id) {
    const origen = document.getElementById('detalle-' + id);
    const cuerpo = document.getElementById('cuerpoDetalle');
    if (!origen || !cuerpo) return;
    cuerpo.innerHTML = origen.innerHTML;
    // Los formularios del detalle se clonan: sus listeners no se copian con HTML.
    cuerpo.querySelectorAll('form[data-confirm]').forEach(form => {
        delete form.dataset.confirmInicializado;
        delete form.dataset.confirmLocked;
    });
    window.initConfirmaciones?.();
    document.getElementById('modalDetalle').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarDetalle() {
    document.getElementById('modalDetalle').style.display = 'none';
    document.body.style.overflow = '';
}

// MODAL RECHAZO
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
        if (/^\d+$/.test(modalDetalle.dataset.reabrir || '')) {
            abrirDetalle(modalDetalle.dataset.reabrir);
        }
    }
});
