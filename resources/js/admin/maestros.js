function filtrarMaestros() {
    const texto  = document.getElementById('buscadorMaestro').value.toLowerCase().trim();
    const filas  = document.querySelectorAll('.fila-maestro');
    let visibles = 0;

    filas.forEach(fila => {
        const nombre = fila.dataset.nombre;
        if (!texto || nombre.includes(texto)) {
            fila.style.display = '';
            visibles++;
        } else {
            fila.style.display = 'none';
        }
    });

    document.getElementById('sinResultados').style.display = visibles === 0 && texto ? 'block' : 'none';
    document.getElementById('contadorMaestros').textContent = texto ? visibles + ' resultado(s)' : '';
}

function limpiarBusquedaMaestros() {
    document.getElementById('buscadorMaestro').value = '';
    document.getElementById('contadorMaestros').textContent = '';
    filtrarMaestros();
}

function abrirModalMaestro() {
    document.getElementById('modalNuevoMaestro').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalMaestro() {
    document.getElementById('modalNuevoMaestro').style.display = 'none';
    document.body.style.overflow = '';
}

function abrirModalEditarMaestro(id, nombre, apellido, codigo, email, telefono, dui) {
    const modal = document.getElementById('modalEditarMaestro');
    const form = document.getElementById('formEditarMaestro');

    document.getElementById('editIdMaestro').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editApellido').value = apellido;
    document.getElementById('editCodigo').value = codigo;
    document.getElementById('editEmail').value = email;
    document.getElementById('editTelefono').value = telefono ?? '';
    document.getElementById('editDui').value = dui ?? '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editPassword_confirmation').value = '';

    form.action = '/admin/maestros/' + id;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalEditarMaestro() {
    const modal = document.getElementById('modalEditarMaestro');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

window.filtrarMaestros = filtrarMaestros;
window.limpiarBusquedaMaestros = limpiarBusquedaMaestros;
window.abrirModalMaestro = abrirModalMaestro;
window.cerrarModalMaestro = cerrarModalMaestro;
window.abrirModalEditarMaestro = abrirModalEditarMaestro;
window.cerrarModalEditarMaestro = cerrarModalEditarMaestro;

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalNuevoMaestro').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalMaestro();
    });

    document.getElementById('modalEditarMaestro').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalEditarMaestro();
    });
});