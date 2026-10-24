function abrirModalMateria() {
    document.getElementById('modalNuevaMateria').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalMateria() {
    document.getElementById('modalNuevaMateria').style.display = 'none';
    document.body.style.overflow = '';
}

function toggleEstado(element, id, estadoActual, url) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;

    fetch(url, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        const activa = data.activa;
        element.textContent = activa ? '● Activa' : '● Inactiva';
        element.style.background = activa ? '#f0fdf4' : '#fef2f2';
        element.style.color = activa ? '#16a34a' : '#dc2626';
        element.setAttribute('onclick', `toggleEstado(this, ${id}, ${activa ? 1 : 0}, '${url}')`);
    })
    .catch(error => {
        console.error('Error al cambiar estado:', error);
    });
}

function limpiarBusquedaMaterias() {
    document.getElementById('buscadorMateria').value = '';
    document.getElementById('contadorMaterias').textContent = '';
    const filas = document.querySelectorAll('.fila-materia');
    filas.forEach(f => f.style.display = '');
    document.getElementById('sinResultados').style.display = 'none';
}

function filtrarMaterias() {
    const texto = document.getElementById('buscadorMateria').value.toLowerCase().trim();
    let visibles = 0;
    document.querySelectorAll('.fila-materia').forEach(fila => {
        const coincide = !texto || fila.textContent.toLowerCase().includes(texto);
        fila.style.display = coincide ? '' : 'none';
        if (coincide) visibles++;
    });
    document.getElementById('contadorMaterias').textContent = texto ? `${visibles} resultados` : '';
    document.getElementById('sinResultados').style.display = visibles ? 'none' : 'block';
}

window.abrirModalMateria = abrirModalMateria;
window.cerrarModalMateria = cerrarModalMateria;
window.toggleEstado = toggleEstado;
window.limpiarBusquedaMaterias = limpiarBusquedaMaterias;
window.filtrarMaterias = filtrarMaterias;

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalNuevaMateria').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalMateria();
    });
});
