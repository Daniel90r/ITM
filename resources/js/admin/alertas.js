import Swal from 'sweetalert2';

const swalBase = {
    confirmButtonColor: '#111827',
    cancelButtonColor: '#374151',
    background: '#ffffff',
    color: '#111827',
    fontFamily: "'DM Sans', sans-serif",
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    buttonsStyling: true,
    customClass: {
        confirmButton: 'rounded-lg px-4 py-2 text-sm font-semibold',
        cancelButton: 'rounded-lg px-4 py-2 text-sm font-semibold',
        popup: 'rounded-2xl',
        title: 'text-lg font-bold',
        content: 'text-sm'
    }
};

function initConfirmaciones() {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        if (form.dataset.confirmInicializado) return;
        form.dataset.confirmInicializado = 'true';

        form.addEventListener('submit', function(e) {
            if (form.dataset.confirmLocked === 'true') return;

            const mensaje = form.dataset.confirm || '¿Estás seguro?';
            const titulo = form.dataset.confirmTitulo || 'Confirmación';
            const boton = form.dataset.confirmBoton || 'Sí, continuar';
            const tipo = form.dataset.confirmTipo || 'aviso';

            const iconMap = {
                exito: 'success',
                peligro: 'warning',
                aviso: 'question'
            };

            e.preventDefault();

            Swal.fire({
                ...swalBase,
                title: titulo,
                text: mensaje,
                icon: iconMap[tipo] || 'question',
                confirmButtonText: boton,
                showCancelButton: true
            }).then(result => {
                if (result.isConfirmed) {
                    form.dataset.confirmLocked = 'true';
                    HTMLFormElement.prototype.submit.call(form);
                }
            });
        });
    });
}

function mostrarConfirmacion({ mensaje = '¿Estás seguro?', titulo = 'Confirmación', boton = 'Sí, continuar', tipo = 'aviso' }) {
    const iconMap = {
        exito: 'success',
        peligro: 'warning',
        aviso: 'question'
    };

    return Swal.fire({
        ...swalBase,
        title: titulo,
        text: mensaje,
        icon: iconMap[tipo] || 'question',
        confirmButtonText: boton,
        showCancelButton: true
    });
}

function mostrarAlerta({ titulo = '', mensaje = '', tipo = 'exito' }) {
    const iconMap = {
        exito: 'success',
        peligro: 'error',
        aviso: 'info'
    };

    Swal.fire({
        ...swalBase,
        title: titulo,
        text: mensaje,
        icon: iconMap[tipo] || 'success',
        confirmButtonText: 'Aceptar',
        showCancelButton: false
    });
}

function mostrarNotificacion(mensaje, tipo = 'exito') {
    const iconMap = {
        exito: 'success',
        peligro: 'error',
        aviso: 'info'
    };

    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: toast => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
        icon: iconMap[tipo] || 'success',
        title: mensaje,
        background: '#1f2937',
        color: '#f9fafb'
    });
}

document.addEventListener('DOMContentLoaded', initConfirmaciones);

window.mostrarConfirmacion = mostrarConfirmacion;
window.mostrarAlerta = mostrarAlerta;
window.mostrarNotificacion = mostrarNotificacion;
window.initConfirmaciones = initConfirmaciones;

export { initConfirmaciones, mostrarAlerta, mostrarNotificacion, mostrarConfirmacion };
