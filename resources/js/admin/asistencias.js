import {initFiltrosAsistencias} from './asistencias-filtros.js';

// Solo se solicitan los detalles al expandir un alumno o cambiar su página.
export function initAsistencias(root) {
    if (!root || root.dataset.inicializado) return;
    root.dataset.inicializado = 'true';
    initFiltrosAsistencias(root);

    async function cargar(panel, url) {
        if (panel.dataset.cargando === 'true') return;
        panel.dataset.cargando = 'true';
        panel.dataset.urlActual = url;
        panel.setAttribute('aria-busy', 'true');
        panel.innerHTML = '<p class="detalle-mensaje" role="status">Cargando asistencias…</p>';
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok || response.redirected) {
                throw new Error([401, 403].includes(response.status) || response.redirected ? 'sesion' : 'carga');
            }
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('carga');
            panel.innerHTML = data.html;
            panel.dataset.cargado = 'true';
        } catch (error) {
            panel.dataset.cargado = 'false';
            const mensaje = document.createElement('p');
            mensaje.className = 'detalle-mensaje';
            mensaje.setAttribute('role', 'alert');
            mensaje.textContent = error.message === 'sesion'
                ? 'Tu sesión expiró o no tienes permiso. Vuelve a iniciar sesión.'
                : 'No fue posible cargar las asistencias. Inténtalo de nuevo.';
            const reintentar = document.createElement('button');
            reintentar.type = 'button';
            reintentar.className = 'reporte-boton';
            reintentar.dataset.detalleReintentar = 'true';
            reintentar.textContent = 'Reintentar';
            panel.replaceChildren(mensaje, reintentar);
        } finally {
            panel.dataset.cargando = 'false';
            panel.setAttribute('aria-busy', 'false');
        }
    }

    root.addEventListener('click', event => {
        const boton = event.target.closest('[data-ver-asistencias]');
        if (boton) {
            const fila = document.getElementById(boton.getAttribute('aria-controls'));
            const panel = fila.querySelector('[data-detalle-panel]');
            const expandir = boton.getAttribute('aria-expanded') !== 'true';
            fila.hidden = !expandir;
            boton.setAttribute('aria-expanded', String(expandir));
            boton.textContent = expandir ? 'Ocultar asistencias' : 'Ver asistencias';
            if (expandir && panel.dataset.cargado !== 'true') cargar(panel, panel.dataset.urlActual || boton.dataset.url);
            return;
        }
        const enlace = event.target.closest('.detalle-paginacion a');
        if (enlace) {
            event.preventDefault();
            cargar(enlace.closest('[data-detalle-panel]'), enlace.href);
            return;
        }
        const reintentar = event.target.closest('[data-detalle-reintentar]');
        if (reintentar) {
            const panel = reintentar.closest('[data-detalle-panel]');
            cargar(panel, panel.dataset.urlActual);
        }
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initAsistencias(document.querySelector('[data-reporte-asistencias]')));
}
