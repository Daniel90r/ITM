import {initConsultaDinamica} from '../utils/consulta-dinamica.js';

export function initFiltrosAlumnos(root) {
    const form=root?.querySelector('#alumnos-alcance');
    if (!form || form.dataset.consultaDinamica) return;
    // Retirar solo los envíos y filtros heredados. Sin JS se conserva el GET original.
    for (const control of form.querySelectorAll('[onchange],[oninput]')) {
        control.removeAttribute('onchange');control.removeAttribute('oninput');
    }
    root.querySelector('.alumnos-limpiar')?.removeAttribute('onclick');
    return initConsultaDinamica({
        root,form,loading:root.querySelector('[data-alumnos-carga]'),error:root.querySelector('[data-alumnos-error]'),
        targets:['[data-alumnos-resultados]','[data-alumnos-resumen]','[data-alumnos-contador]','#alumnos-curso-consulta','#filtrarAnio'],
        limpiar:'.alumnos-limpiar',
        change(control,form) {
            if (control.name==='incluir_historicos' && !control.checked) {
                const curso=form.querySelector('[name="curso_id"]');
                if (curso.selectedOptions[0]?.dataset.estadoCurso==='FINALIZADO') curso.value='';
            }
        },
    });
}
