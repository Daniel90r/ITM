// Presentación del flujo institucional existente: no cambia inscripciones.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-inscrito-estado]').forEach(form => {
        if (form.dataset.inicializado) return;
        form.dataset.inicializado = 'true';
        const button = form.querySelector('button[type="submit"]');
        const error = form.querySelector('[data-estado-error]');
        const label = button.textContent;
        const reset = () => {
            delete form.dataset.busy;
            button.disabled = false;
            button.textContent = label;
            form.removeAttribute('aria-busy');
        };
        window.addEventListener('pageshow', reset);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.busy) return;
            form.dataset.busy = 'true';
            error.textContent = '';
            let enviado = false;
            try {
                if (typeof window.mostrarConfirmacion !== 'function') throw Error('Confirmación no disponible');
                const activar = form.dataset.accion === 'activar';
                const result = await window.mostrarConfirmacion({
                    titulo: activar ? '¿Estás seguro de activar a este alumno?' : '¿Estás seguro de desactivar a este alumno?',
                    mensaje: `${form.dataset.nombre}. Se conserva su inscripción e historial académico y económico.`,
                    boton: activar ? 'Sí, activar' : 'Sí, desactivar',
                    tipo: activar ? 'exito' : 'peligro',
                    opciones: {
                        input: 'textarea', inputLabel: 'Motivo del cambio (obligatorio)',
                        inputAttributes: {maxlength: '500'}, focusCancel: true, returnFocus: true, allowOutsideClick: false,
                        inputValidator: value => {
                            const length = [...value.trim()].length;
                            if (length < 10 || length > 500) return 'Ingresa un motivo de entre 10 y 500 caracteres.';
                        },
                    },
                });
                if (!result.isConfirmed) return;
                form.querySelector('[name="motivo"]').value = result.value.trim();
                button.disabled = true;
                button.textContent = 'Procesando…';
                form.setAttribute('aria-busy', 'true');
                HTMLFormElement.prototype.submit.call(form);
                enviado = true;
            } catch {
                error.textContent = 'No se pudo enviar la solicitud. Inténtalo de nuevo o recarga la página.';
            } finally {
                if (!enviado) reset();
            }
        });
    });
});
