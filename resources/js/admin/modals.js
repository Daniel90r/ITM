// Modal functions for Alumno
function abrirModalNuevoAlumno() {
    document.getElementById('modalNuevoAlumno').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalNuevoAlumno() {
    document.getElementById('modalNuevoAlumno').style.display = 'none';
    document.body.style.overflow = '';
}

// Modal functions for Maestros
function abrirModalMaestro() {
    document.getElementById('modalNuevoMaestro').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalMaestro() {
    document.getElementById('modalNuevoMaestro').style.display = 'none';
    document.body.style.overflow = '';
}

// Modal functions for Editar Maestro
function abrirModalEditarMaestro(id, nombre, apellido, telefono, dui, codigo, email) {
    document.getElementById('editIdMaestro').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editApellido').value = apellido;
    document.getElementById('editTelefono').value = telefono || '';
    document.getElementById('editDui').value = dui || '';
    document.getElementById('editCodigo').value = codigo;
    document.getElementById('editEmail').value = email;
    const password = document.getElementById('editPassword');
    const passwordConfirmation = document.getElementById('editPasswordConfirmation');

    if (password) {
        password.value = '';
    }

    if (passwordConfirmation) {
        passwordConfirmation.value = '';
    }
    document.getElementById('formEditarMaestro').action = '/admin/maestros/' + id;
    document.getElementById('modalEditarMaestro').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalEditarMaestro() {
    document.getElementById('modalEditarMaestro').style.display = 'none';
    document.body.style.overflow = '';
}

// Modal functions for Asignar Curso
function abrirModalAsignar(maestroId, maestroNombre) {
    document.getElementById('formAsignarCurso').action = '/admin/maestros/' + maestroId + '/asignar';
    document.getElementById('tituloModalAsignar').textContent = 'Asignar curso a ' + maestroNombre;
    document.getElementById('modalAsignarCurso').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalAsignarCurso() {
    document.getElementById('modalAsignarCurso').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('formAsignarCurso').reset();
}

window.abrirModalNuevoAlumno = abrirModalNuevoAlumno;
window.cerrarModalNuevoAlumno = cerrarModalNuevoAlumno;
window.abrirModalMaestro = abrirModalMaestro;
window.cerrarModalMaestro = cerrarModalMaestro;
window.abrirModalEditarMaestro = abrirModalEditarMaestro;
window.cerrarModalEditarMaestro = cerrarModalEditarMaestro;
window.abrirModalAsignar = abrirModalAsignar;
window.cerrarModalAsignarCurso = cerrarModalAsignarCurso;

document.addEventListener('DOMContentLoaded', function() {
    var modalAsignar = document.getElementById('modalAsignarCurso');
    if (modalAsignar) {
        modalAsignar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalAsignarCurso();
        });
    }
});