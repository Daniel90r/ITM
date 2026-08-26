// Modal functions for Alumno
function abrirModalNuevoAlumno() {
    const modal = document.getElementById('modalNuevoAlumno');
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalNuevoAlumno() {
    const modal = document.getElementById('modalNuevoAlumno');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Maestros
function abrirModalMaestro() {
    const modal = document.getElementById('modalNuevoMaestro');
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalMaestro() {
    const modal = document.getElementById('modalNuevoMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Editar Maestro
function abrirModalEditarMaestro(id, nombre, apellido, telefono, dui, codigo, email) {
    const editId = document.getElementById('editIdMaestro');
    const editNombre = document.getElementById('editNombre');
    const editApellido = document.getElementById('editApellido');
    const editTelefono = document.getElementById('editTelefono');
    const editDui = document.getElementById('editDui');
    const editCodigo = document.getElementById('editCodigo');
    const editEmail = document.getElementById('editEmail');
    const password = document.getElementById('editPassword');
    const passwordConfirmation = document.getElementById('editPasswordConfirmation');
    const form = document.getElementById('formEditarMaestro');
    const modal = document.getElementById('modalEditarMaestro');

    if (editId) editId.value = id;
    if (editNombre) editNombre.value = nombre;
    if (editApellido) editApellido.value = apellido;
    if (editTelefono) editTelefono.value = telefono || '';
    if (editDui) editDui.value = dui || '';
    if (editCodigo) editCodigo.value = codigo;
    if (editEmail) editEmail.value = email;

    if (password) password.value = '';
    if (passwordConfirmation) passwordConfirmation.value = '';

    if (form) form.action = '/admin/maestros/' + id;
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalEditarMaestro() {
    const modal = document.getElementById('modalEditarMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

// Modal functions for Asignar Curso
function abrirModalAsignar(maestroId, maestroNombre) {
    const form = document.getElementById('formAsignarCurso');
    const titulo = document.getElementById('tituloModalAsignar');
    const modal = document.getElementById('modalAsignarCurso');

    if (form) form.action = '/admin/maestros/' + maestroId + '/asignar';
    if (titulo) titulo.textContent = 'Asignar curso a ' + maestroNombre;
    if (modal) {
        modal.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
}

function cerrarModalAsignarCurso() {
    const modal = document.getElementById('modalAsignarCurso');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    const form = document.getElementById('formAsignarCurso');
    if (form) {
        form.reset();
    }
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
    const modalNuevo = document.getElementById('modalNuevoMaestro');
    if (modalNuevo) {
        modalNuevo.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalMaestro();
        });
    }

    const modalEditar = document.getElementById('modalEditarMaestro');
    if (modalEditar) {
        modalEditar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalEditarMaestro();
        });
    }

    const modalAsignar = document.getElementById('modalAsignarCurso');
    if (modalAsignar) {
        modalAsignar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalAsignarCurso();
        });
    }
});