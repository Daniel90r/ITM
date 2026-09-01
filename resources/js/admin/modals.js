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

// Modal functions for Asignar programa
function abrirModalAsignarCurso() {
    const modal = document.getElementById('modalAsignarCurso');

    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
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

// Modal functions for Cambiar Maestro
function abrirModalCambiarMaestro(ruta, detalleId, cursoNombre, programaNombre) {
    const modal = document.getElementById('modalCambiarMaestro');
    const form = document.getElementById('formCambiarMaestro');

    if (!modal || !form) {
        return;
    }

    form.action = ruta;
    const detalleInput = document.getElementById('detalleIdContexto');
    if (detalleInput) detalleInput.value = detalleId;

    const cursoSpan = document.getElementById('contextoCurso');
    const programaSpan = document.getElementById('contextoPrograma');
    if (cursoSpan) cursoSpan.textContent = cursoNombre;
    if (programaSpan) programaSpan.textContent = programaNombre;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalCambiarMaestro() {
    const modal = document.getElementById('modalCambiarMaestro');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    const form = document.getElementById('formCambiarMaestro');
    if (form) {
        form.reset();
        form.action = '';
    }
    const detalleInput = document.getElementById('detalleIdContexto');
    if (detalleInput) detalleInput.value = '';
    const cursoSpan = document.getElementById('contextoCurso');
    const programaSpan = document.getElementById('contextoPrograma');
    if (cursoSpan) cursoSpan.textContent = '';
    if (programaSpan) programaSpan.textContent = '';
}

window.abrirModalNuevoAlumno = abrirModalNuevoAlumno;
window.cerrarModalNuevoAlumno = cerrarModalNuevoAlumno;
window.abrirModalMaestro = abrirModalMaestro;
window.cerrarModalMaestro = cerrarModalMaestro;
window.abrirModalEditarMaestro = abrirModalEditarMaestro;
window.cerrarModalEditarMaestro = cerrarModalEditarMaestro;
window.abrirModalAsignarCurso = abrirModalAsignarCurso;
window.cerrarModalAsignarCurso = cerrarModalAsignarCurso;
window.abrirModalCambiarMaestro = abrirModalCambiarMaestro;
window.cerrarModalCambiarMaestro = cerrarModalCambiarMaestro;

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

        if (modalAsignar.dataset.open === '1') {
            abrirModalAsignarCurso();
        }
    }

    const modalCambiar = document.getElementById('modalCambiarMaestro');
    if (modalCambiar) {
        modalCambiar.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalCambiarMaestro();
        });

        if (modalCambiar.dataset.open === '1' && modalCambiar.dataset.action) {
            modalCambiar.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modalAsignarAbierto = document.getElementById('modalAsignarCurso');
            if (modalAsignarAbierto && modalAsignarAbierto.style.display === 'flex') {
                cerrarModalAsignarCurso();
            }
            const modalCambiarAbierto = document.getElementById('modalCambiarMaestro');
            if (modalCambiarAbierto && modalCambiarAbierto.style.display === 'flex') {
                cerrarModalCambiarMaestro();
            }
        }
    });
});
