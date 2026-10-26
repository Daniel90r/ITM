import {initFiltrosAlumnos} from './alumnos-filtros.js';

function abrirModalEditar(id, nombre, apellido, email, cursoId, telefono, cursoNombre) {
    document.getElementById('editId').value       = id;
    document.getElementById('editNombre').value   = nombre;
    document.getElementById('editApellido').value = apellido;
    document.getElementById('editEmail').value    = email;
    if (document.getElementById('editCursoDisplay')) {
        document.getElementById('editCursoDisplay').value = cursoNombre || 'Sin curso asignado';
    }
    document.getElementById('editTelefono').value = telefono ?? '';
    document.getElementById('editPassword').value = '';
    document.getElementById('formEditarAlumno').action = '/admin/alumnos/' + id;
    document.getElementById('modalEditarAlumno').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalEditar() {
    document.getElementById('modalEditarAlumno').style.display = 'none';
    document.body.style.overflow = '';
}

function abrirModalNuevo() {
    document.getElementById('modalNuevoAlumno').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalNuevo() {
    document.getElementById('modalNuevoAlumno').style.display = 'none';
    document.body.style.overflow = '';
}

function filtrarAlumnos() {
    const nombre    = document.getElementById('buscarAlumno').value.toLowerCase().trim();
    const curso     = document.getElementById('filtrarCurso').value.toLowerCase();
    const anio      = document.getElementById('filtrarAnio').value;
    const filas     = document.querySelectorAll('.fila-alumno');
    let visibles    = 0;

    filas.forEach(fila => {
        const okNombre = !nombre || fila.dataset.nombre.includes(nombre);
        const okCurso  = !curso  || fila.dataset.curso.includes(curso);
        const okAnio   = !anio   || fila.dataset.anio === anio;

        if (okNombre && okCurso && okAnio) {
            fila.style.display = '';
            visibles++;
        } else {
            fila.style.display = 'none';
        }
    });

    const hayFiltro = nombre || curso || anio;
    document.getElementById('sinResultados').style.display   = visibles === 0 && hayFiltro ? 'block' : 'none';
    document.getElementById('contadorResultados').style.display = hayFiltro ? 'block' : 'none';
    document.getElementById('contadorResultados').textContent   = hayFiltro ? visibles + ' alumno(s) encontrado(s)' : '';
    document.getElementById('contadorHeader').textContent = visibles + ' alumno(s)';
}

function limpiarFiltrosAlumnos() {
    const alcance = document.getElementById('alumnos-alcance');
    if (alcance) { window.location.assign(alcance.action); return; }
    document.getElementById('buscarAlumno').value = '';
    document.getElementById('filtrarCurso').value = '';
    document.getElementById('filtrarAnio').value  = '';
    document.getElementById('sinResultados').style.display      = 'none';
    document.getElementById('contadorResultados').style.display = 'none';
    document.querySelectorAll('.fila-alumno').forEach(f => f.style.display = '');
    document.getElementById('contadorHeader').textContent = document.getElementById('contadorHeader').dataset.total;
}

window.abrirModalEditar = abrirModalEditar;
window.cerrarModalEditar = cerrarModalEditar;
window.abrirModalNuevo = abrirModalNuevo;
window.cerrarModalNuevo = cerrarModalNuevo;
window.filtrarAlumnos = filtrarAlumnos;
window.limpiarFiltrosAlumnos = limpiarFiltrosAlumnos;

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosAlumnos(document);
    const modalNuevo = document.getElementById('modalNuevoAlumno');
    const modalEditar = document.getElementById('modalEditarAlumno');
    if (modalNuevo) {
        modalNuevo.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalNuevo();
        });
    }
    if (modalEditar) {
        modalEditar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalEditar();
        });
    }
});
