/* Solo presentación: conserva los valores y el envío nativo de los formularios. */
(() => {
    function init() {
        document.querySelectorAll('.estado-persona [data-persona-form]').forEach(form => {
            if (form.dataset.personaInicializado) return;
            form.dataset.personaInicializado = 'true';
            const reason = form.querySelector('[data-persona-reason]');
            const counter = form.querySelector('[data-persona-counter]');
            const button = form.querySelector('button[type="submit"]');
            const label = form.querySelector('[data-persona-button-label]');
            const status = form.querySelector('[data-persona-status]');
            const originalLabel = label?.textContent;
            const originallyDisabled = button?.disabled;
            const updateCounter = () => {
                if (reason && counter) counter.textContent = `${reason.value.length} / ${reason.maxLength}`;
            };
            reason?.addEventListener('input', updateCounter);
            updateCounter();
            form.addEventListener('submit', async event => {
                event.preventDefault();
                if (form.dataset.processing === 'true' || form.dataset.confirming === 'true' || button?.disabled) return;
                if (!form.reportValidity()) return;
                if (typeof window.mostrarConfirmacion !== 'function') {
                    if (status) status.textContent = 'No se pudo abrir la confirmación. Recarga la página e inténtalo de nuevo.';
                    return;
                }
                const accion = form.dataset.personaAccion;
                const reactivar = accion === 'reactivar', finalizar = accion === 'finalizar';
                const nombre = form.dataset.personaNombre;
                form.dataset.confirming = 'true';
                try {
                    const result = await window.mostrarConfirmacion({
                        titulo: finalizar ? '¿Finalizar la asignación programada?' : (reactivar ? '¿Reactivar el acceso?' : '¿Desactivar el acceso?'),
                        mensaje: finalizar ? form.dataset.personaConfirm : `${nombre}. ${reactivar
                            ? 'Se permitirá el acceso con un nuevo inicio de sesión. No se restablecen matrículas ni asignaciones finalizadas.'
                            : 'Se bloqueará el acceso institucional y se revocarán las sesiones abiertas.'} Los registros académicos y económicos se conservan.`,
                        boton: finalizar ? 'Sí, finalizar' : (reactivar ? 'Sí, reactivar' : 'Sí, desactivar'),
                        tipo: reactivar ? 'exito' : 'peligro',
                        opciones: {
                            confirmButtonColor: reactivar ? '#166534' : '#991b1b', cancelButtonColor: '#f1f5f9',
                            focusCancel: true, returnFocus: true, allowEscapeKey: true, allowOutsideClick: false,
                            customClass: {popup: 'ep-confirm-dialog', confirmButton: 'ep-confirm-primary', cancelButton: 'ep-confirm-cancel'},
                        },
                    });
                    if (!result.isConfirmed || !form.reportValidity()) return;
                    form.dataset.processing = 'true';
                    form.setAttribute('aria-busy', 'true');
                    if (button) button.disabled = true;
                    if (label) label.textContent = 'Procesando…';
                    if (status) status.textContent = 'Enviando solicitud. Espera un momento.';
                    HTMLFormElement.prototype.submit.call(form);
                } catch {
                    delete form.dataset.processing;
                    form.removeAttribute('aria-busy');
                    if (button) button.disabled = originallyDisabled;
                    if (label) label.textContent = originalLabel;
                    if (status) status.textContent = 'No se pudo enviar la solicitud. Inténtalo de nuevo.';
                } finally { delete form.dataset.confirming; }
            });
            window.addEventListener('pageshow', () => {
                delete form.dataset.processing;
                delete form.dataset.confirming;
                form.removeAttribute('aria-busy');
                if (button) button.disabled = originallyDisabled;
                if (label) label.textContent = originalLabel;
                if (status) status.textContent = '';
                updateCounter();
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
