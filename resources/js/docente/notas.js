document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('buscadorCurso')) {
        const input = document.getElementById('buscadorCurso');
        const wrapper = input.closest('div').parentElement;
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                document.getElementById('dropdownCursos').style.display = 'none';
            }
        });
    }

    if (document.getElementById('selectCurso')) {
        const cursoId = document.getElementById('selectCurso').value;
        if (cursoId) mostrarMaterias(cursoId);
    }

    document.querySelectorAll('.asistencia-row')?.forEach(row => actualizarEstado(row.querySelector('select')));
});

window.filtrarMaterias = function() {
    const cursoId = document.getElementById('selectCurso').value;
    const selectMateria = document.getElementById('selectMateria');
    selectMateria.value = '';
    document.getElementById('inputDetalleCursoId').value = '';
    if (!cursoId) {
        selectMateria.querySelectorAll('option[data-curso]').forEach(o => o.style.display = 'none');
        selectMateria.querySelector('option[value=""]').textContent = '-- Primero selecciona un curso --';
        return;
    }
    mostrarMaterias(cursoId);
};

window.mostrarMaterias = function(cursoId) {
    const selectMateria = document.getElementById('selectMateria');
    const options = selectMateria.querySelectorAll('option[data-curso]');
    selectMateria.querySelector('option[value=""]').textContent = '-- Seleccionar materia --';
    options.forEach(opt => {
        opt.style.display = opt.dataset.curso === cursoId ? '' : 'none';
    });
};

window.submitFiltro = function() {
    const detalleCursoId = document.getElementById('selectMateria').value;
    if (!detalleCursoId) return;
    document.getElementById('inputDetalleCursoId').value = detalleCursoId;
    document.getElementById('filtroForm').submit();
};

window.promColor = function(p) {
    if (p >= 9) return '#10b981';
    if (p >= 7) return '#f59e0b';
    if (p >= 6) return '#f97316';
    return '#ef4444';
};

window.calcProm = function(row) {
    const inputs = row.querySelectorAll('.nota-input');
    const vals = [];
    inputs.forEach(i => {
        const v = parseFloat(i.value);
        if (!isNaN(v) && i.value.trim() !== '') vals.push(Math.min(10, Math.max(0, v)));
    });
    const badge = document.getElementById('prom-' + row.dataset.alumno);
    if (!vals.length) {
        badge.textContent = '—';
        badge.style.background = '#f3f4f6';
        badge.style.color = '#9ca3af';
        return;
    }
    const p = Math.round(vals.reduce((a,b) => a+b, 0) / vals.length);
    badge.textContent = p;
    badge.style.background = promColor(p);
    badge.style.color = '#fff';
};

window.colorPorEstado = function(estado) {
    if (estado === 'ausente') return '#fee2e2';
    if (estado === 'permiso') return '#fef3c7';
    return '#dcfce7';
};

window.textoPorEstado = function(estado) {
    if (estado === 'ausente') return 'Ausente';
    if (estado === 'permiso') return 'Con permiso';
    return 'Presente';
};

window.actualizarEstado = function(select) {
    const estado = select.value;
    const row = select.closest('tr');
    row.dataset.estado = estado;
    row.style.background = colorPorEstado(estado);
};

window.marcarTodos = function(estado) {
    document.querySelectorAll('select[name^="asistencias["][name$="[estado]"]').forEach(select => {
        select.value = estado;
        actualizarEstado(select);
    });
};
