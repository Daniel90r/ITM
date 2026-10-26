// Mejora progresiva del GET existente, limitada a /admin/asistencias.
export function initFiltrosAsistencias(root) {
    const form = root.querySelector?.('[data-asistencias-filtros]');
    if (!form || form.dataset.ajaxInicializado) return;
    form.dataset.ajaxInicializado = 'true';
    const results = root.querySelector('[data-asistencias-resultados]');
    const loading = root.querySelector('[data-asistencias-carga]');
    const error = root.querySelector('[data-asistencias-error]');
    let controller, sequence = 0, pendingUrl;

    function sincronizarFormulario(url) {
        for (const input of form.querySelectorAll('input[name],select[name]')) {
            if (input.type === 'hidden') continue;
            if (input.type === 'checkbox') {
                input.checked = url.searchParams.get(input.name) === '1';
                continue;
            }
            input.value = url.searchParams.get(input.name) ?? '';
        }
    }

    async function cargar(url, historyMode = 'push', focus = false) {
        url = new URL(url, location.href);
        if (url.origin !== location.origin || url.pathname !== new URL(form.action).pathname) return;
        if (pendingUrl === url.href) return;
        controller?.abort();
        controller = new AbortController();
        const current = ++sequence;
        pendingUrl = url.href;
        results.setAttribute('aria-busy', 'true');
        loading.textContent = 'Consultando asistencias…';
        error.textContent = '';
        try {
            const response = await fetch(url.href, {
                signal: controller.signal, credentials: 'same-origin',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            if (current !== sequence) return;
            if (response.status === 422) {
                const data = await response.json();
                if (current !== sequence) return;
                error.textContent = Object.values(data.errors ?? {}).flat().join(' ') || 'Revisa los filtros ingresados.';
                return;
            }
            if (response.redirected || [401,403].includes(response.status)) throw new Error('sesion');
            if (!response.ok) throw new Error('carga');
            const html = await response.text();
            if (current !== sequence) return;
            const page = new DOMParser().parseFromString(html, 'text/html');
            const next = page.querySelector('[data-asistencias-resultados]');
            if (!next) throw new Error('carga');
            results.innerHTML = next.innerHTML;
            for (const selector of ['[data-consulta-cursos-alcance]','#asistencia-curso','[data-asistencias-materias]','[data-asistencia-exportacion]','[data-asistencia-periodo]']) {
                const target = root.querySelector(selector), source = page.querySelector(selector);
                if (target && source) {target.innerHTML = source.innerHTML;target.hidden = source.hidden;}
            }
            sincronizarFormulario(url);
            // Un enlace histórico puede activar el alcance en el servidor.
            const historial = form.querySelector('input[type="checkbox"][name="incluir_historicos"]');
            const aplicado = page.querySelector('input[type="checkbox"][name="incluir_historicos"]');
            if (historial && aplicado) historial.checked = aplicado.checked;
            if (aplicado?.checked) url.searchParams.set('incluir_historicos', '1');
            if (historyMode === 'push' && url.href !== location.href) history.pushState(null, '', url.href);
            loading.textContent = 'Resultados actualizados.';
            if (focus) results.focus({preventScroll: true});
        } catch (failure) {
            if (current !== sequence || failure.name === 'AbortError') return;
            error.textContent = failure.message === 'sesion'
                ? 'Tu sesión expiró o no tienes permiso. Vuelve a iniciar sesión.'
                : 'No fue posible actualizar las asistencias. Inténtalo de nuevo o recarga la página.';
        } finally {
            if (current === sequence) {
                pendingUrl = undefined;
                results.setAttribute('aria-busy', 'false');
                if (error.textContent) loading.textContent = '';
            }
        }
    }

    function filtrar() {
        const url = new URL(form.action);
        for (const [name,value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') url.searchParams.set(name,value);
        }
        cargar(url);
    }
    form.addEventListener('submit', event => {event.preventDefault();filtrar();});
    form.addEventListener('change', event => {
        if (event.target.name === 'curso_id') {
            const materia = form.querySelector('[name="detalle_curso_id"]');
            if (materia) materia.value = '';
            filtrar();
        }
    });
    root.addEventListener('click', event => {
        if (event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const clear = event.target.closest('[data-asistencias-limpiar]');
        const page = event.target.closest('[data-asistencias-resultados] .listado-footer a');
        if (!clear && !page) return;
        event.preventDefault();
        cargar((clear ?? page).href, 'push', !!page);
    });
    window.addEventListener('popstate', () => {
        sincronizarFormulario(new URL(location.href));
        cargar(location.href, 'none');
    });
}
