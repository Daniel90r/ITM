import '../../css/admin/horarios-curso.css';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-horario-semanal]').forEach(editor => {
        const list = editor.querySelector('[data-horario-lista]');
        const empty = editor.querySelector('[data-horario-vacio]');
        const status = editor.querySelector('[data-horario-status]');
        let index = Number(editor.dataset.siguienteIndice);
        const refresh = message => {
            empty.hidden = !!list.querySelector('[data-horario-bloque]');
            status.textContent = message;
        };
        editor.querySelector('[data-agregar-horario]').addEventListener('click', () => {
            if (editor.disabled) return;
            const html = editor.querySelector('template').innerHTML.replaceAll('__INDEX__', String(index++));
            list.insertAdjacentHTML('beforeend', html);
            refresh('Bloque agregado.');
            list.lastElementChild.querySelector('select').focus();
        });
        editor.addEventListener('click', event => {
            const button = event.target.closest('[data-quitar-horario]');
            if (!button || editor.disabled) return;
            button.closest('[data-horario-bloque]').remove();
            refresh('Bloque quitado.');
            editor.querySelector('[data-agregar-horario]').focus();
        });
    });
});
