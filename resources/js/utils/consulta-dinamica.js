// Mejora progresiva de consultas GET HTML. No inserta scripts ni reemplaza el layout.
export function initConsultaDinamica({root, form, targets, loading, error, limpiar, change, debounce = 300}) {
    if (!form || form.dataset.consultaDinamica) return;
    form.dataset.consultaDinamica = 'true';
    let controller, revision = 0, pending, timer;

    function urlFormulario() {
        const url = new URL(form.action);
        for (const [name,value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') url.searchParams.set(name,value);
        }
        return url;
    }

    async function cargar(destino, mode = 'push') {
        clearTimeout(timer);
        const url = new URL(destino, location.href);
        if (url.origin !== location.origin || url.pathname !== new URL(form.action).pathname || pending === url.href) return;
        controller?.abort();controller = new AbortController();
        const current = ++revision;pending = url.href;
        const resultados = root.querySelector(targets[0]);
        resultados.setAttribute('aria-busy','true');loading.textContent = 'Consultando alumnos…';error.textContent = '';
        try {
            const response = await fetch(url.href, {signal:controller.signal,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (current !== revision) return;
            if (response.status === 422) {
                const data = await response.json();if (current !== revision) return;
                error.textContent = Object.values(data.errors ?? {}).flat().join(' ') || 'Revisa los filtros ingresados.';return;
            }
            if (response.redirected || [401,403].includes(response.status)) throw Error('sesion');
            if (!response.ok) throw Error('consulta');
            const html = await response.text();if (current !== revision) return;
            const page = new DOMParser().parseFromString(html,'text/html');
            const applied = page.querySelector('#alumnos-alcance');
            if (!applied) throw Error('consulta');
            const pares = targets.map(selector => [root.querySelector(selector),page.querySelector(selector)]);
            if (pares.some(([target,source]) => !target || !source)) throw Error('consulta');
            // Validar todos los fragmentos antes de actualizar; nunca insertar el documento completo.
            for (const [target,source] of pares) target.innerHTML = source.innerHTML;
            for (const input of form.querySelectorAll('input[name],select[name]')) {
                if (input.type === 'hidden') continue;
                const source = applied.querySelector(`[name="${input.name}"][type="checkbox"]`) || applied.querySelector(`[name="${input.name}"]`);
                if (!source) continue;
                if (input.type === 'checkbox') input.checked = source.checked;
                else input.value = source.value;
            }
            const historical = form.querySelector('[name="incluir_historicos"][type="checkbox"]');
            if (historical?.checked) url.searchParams.set('incluir_historicos','1');
            if (mode === 'push' && url.href !== location.href) history.pushState(null,'',url.href);
            loading.textContent = 'Resultados actualizados.';
        } catch (failure) {
            if (current !== revision || failure.name === 'AbortError') return;
            error.textContent = failure.message === 'sesion' ? 'Tu sesión expiró o no tienes permiso. Vuelve a iniciar sesión.' : 'No fue posible actualizar los alumnos. Inténtalo de nuevo o recarga la página.';
        } finally {
            if (current === revision) {pending=undefined;resultados.setAttribute('aria-busy','false');if (error.textContent) loading.textContent='';}
        }
    }

    function inmediato() { clearTimeout(timer);cargar(urlFormulario()); }
    form.addEventListener('submit',event=>{event.preventDefault();inmediato();});
    form.addEventListener('input',event=>{
        if (event.target.name !== 'q') return;
        clearTimeout(timer);
        // Invalidar una respuesta en vuelo desde la primera pulsación, no después del debounce.
        controller?.abort();revision++;pending=undefined;
        root.querySelector(targets[0]).setAttribute('aria-busy','false');loading.textContent='';
        timer=setTimeout(inmediato,debounce);
    });
    form.addEventListener('change',event=>{
        if (!['curso_id','anio','incluir_historicos'].includes(event.target.name)) return;
        change?.(event.target,form);inmediato();
    });
    root.addEventListener('click',event=>{
        if (event.button>0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const clear=event.target.closest(limpiar),page=event.target.closest('[data-alumnos-resultados] a[data-page], [data-alumnos-resultados] .pagination a, [data-alumnos-resultados] nav a');
        if (!clear && !page) return;
        event.preventDefault();cargar(clear ? form.action : page.href);
    });
    window.addEventListener('popstate',()=>cargar(location.href,'none'));
    return {cargar, filtrar:inmediato};
}
