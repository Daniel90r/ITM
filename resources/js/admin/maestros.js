import './modals.js';

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

window.filtrarMaestros = filtrarMaestros;
window.limpiarBusquedaMaestros = limpiarBusquedaMaestros;
