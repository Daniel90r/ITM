/* Interacción compartida. Cada endpoint conserva el alcance autorizado de su módulo. */
(() => {
    let sequence = 0;
    function init(config) {
        const input = document.querySelector(config.selector);
        if (!input || input.dataset.itmAutocomplete) return;
        input.dataset.itmAutocomplete = 'true'; input.classList.add('itm-autocomplete-input');
        const form = input.form, selected = config.selected && form?.elements[config.selected];
        const list = document.createElement('ul'), status = document.createElement('span');
        list.className = 'itm-autocomplete-list'; list.hidden = true; list.id = `itm-sugerencias-${++sequence}`;
        list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', 'Sugerencias de búsqueda');
        status.className = 'itm-autocomplete-status'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        document.body.append(list, status);
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id); input.setAttribute('aria-expanded', 'false'); input.autocomplete = 'off';
        if (!input.labels?.length && !input.hasAttribute('aria-label')) input.setAttribute('aria-label', input.placeholder || 'Buscar');
        if (config.submit) {
            // Este campo consulta por AJAX mientras se escribe. Los manejadores
            // heredados enviaban el formulario con otro temporizador (400 ms).
            // Los filtros locales conservan sus eventos y el formulario conserva
            // su envío manual y los cambios de sus demás controles.
            for (const event of ['oninput', 'onchange', 'onkeyup']) input.removeAttribute(event);
        }
        if (config.courseCallback) {
            // Sustituye el desplegable previo, conservando su función de selección y limpieza.
            input.removeAttribute('oninput'); input.removeAttribute('onfocus');
        }
        let timer, controller, revision = 0, items = [], active = -1, choosing = false;
        function close() {
            clearTimeout(timer); controller?.abort(); revision++;
            if (list.matches?.(':popover-open')) list.hidePopover();
            list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1;
        }
        if (typeof list.showPopover === 'function') list.setAttribute('popover', 'manual');
        function place() {
            const r = input.getBoundingClientRect(), below = innerHeight - r.bottom - 8;
            const above = below < 150 && r.top > below, available = above ? r.top - 8 : below;
            const height = Math.min(320, Math.max(60, available));
            Object.assign(list.style, {width: `${Math.min(r.width, innerWidth - 16)}px`, left: `${Math.max(8, Math.min(r.left, innerWidth - r.width - 8))}px`,
                maxHeight: `${height}px`, top: above ? 'auto' : `${r.bottom + 4}px`, bottom: above ? `${innerHeight - r.top + 4}px` : 'auto'});
        }
        function choose(index) {
            const item = items[index]; if (!item || list.hidden) return;
            choosing = true;
            input.value = config.submit ? (item.codigo || item.nombre).slice(0, 100) : item.nombre;
            if (selected) selected.value = item.id;
            if (config.courseCallback) window.seleccionarCurso?.(item.nombre.toLowerCase(), item.nombre);
            else input.dispatchEvent(new Event('input', {bubbles: true}));
            close(); choosing = false;
            if (config.submit) form?.requestSubmit();
        }
        function highlight(index) {
            active = index;
            [...list.children].forEach((li, i) => li.setAttribute('aria-selected', String(i === active)));
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({block: 'nearest'});
        }
        async function search() {
            const current = ++revision; controller = new AbortController();
            const url = new URL(config.url, location.href), params = new URLSearchParams();
            if (form) for (const [key, value] of new FormData(form)) if (typeof value === 'string' && !['page', 'alumno_id', '_token'].includes(key)) params.set(key, value);
            for (const [key, selector] of Object.entries(config.params || {})) params.set(key, document.querySelector(selector)?.value || '');
            if (config.contextCourse) params.set('curso_id', document.getElementById('vistaAlumnos')?.dataset.cursoActual || '');
            params.set('q', input.value.trim()); url.search = params;
            status.textContent = 'Buscando…';
            try {
                const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {Accept: 'application/json'}});
                if (!response.ok) throw new Error('No disponible');
                const data = await response.json(); if (current !== revision) return;
                items = data.alumnos.slice(0, 8); active = -1; list.replaceChildren();
                items.forEach((item, i) => {
                    const li = document.createElement('li'); li.id = `${list.id}-${i}`;
                    li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
                    const avatar = document.createElement('span'), detail = document.createElement('span');
                    avatar.className = 'itm-autocomplete-avatar'; avatar.setAttribute('aria-hidden', 'true');
                    avatar.textContent = item.nombre.split(/\s+/u).slice(0, 2).map(n => n[0]).join('').toUpperCase();
                    const name = document.createElement('strong'), code = document.createElement('small');
                    name.textContent = item.nombre; code.textContent = item.codigo || '';
                    detail.append(name, code); li.append(avatar, detail);
                    li.addEventListener('pointerdown', event => event.preventDefault()); li.addEventListener('click', () => choose(i)); list.append(li);
                });
                status.textContent = items.length ? `${items.length} resultados encontrados.` : 'No se encontraron coincidencias';
                if (!items.length) { const li = document.createElement('li'); li.setAttribute('role', 'presentation'); li.textContent = status.textContent; list.append(li); }
                list.hidden = false; place(); if (list.hasAttribute('popover')) list.showPopover();
                input.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (error.name !== 'AbortError' && current === revision) { close(); status.textContent = 'No se pudieron cargar las sugerencias. Puede continuar con el filtro habitual.'; }
            }
        }
        function schedule() { close(); status.textContent = ''; if ([...input.value.trim()].length >= 2) timer = setTimeout(search, 300); }
        input.addEventListener('input', () => {
            if (choosing) return;
            if (selected) selected.value = '';
            if (config.courseCallback && window.seleccionarCurso) {
                const draft = input.value; window.seleccionarCurso('', 'Todos los cursos'); input.value = draft; window.filtrarTablas?.();
            }
            schedule();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (list.hidden || !items.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault(); event.stopPropagation();
                highlight(active < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
            } else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); event.stopPropagation(); choose(active); }
        });
        const dependencies = new Set(Object.values(config.params || {}).map(s => document.querySelector(s)).filter(Boolean));
        if (form) for (const control of form.elements) if (control !== input && control.type !== 'hidden') dependencies.add(control);
        dependencies.forEach(control => control.addEventListener('change', schedule));
        input.addEventListener('blur', close); form?.addEventListener('submit', close);
        document.addEventListener('click', event => { if (event.target !== input && !list.contains(event.target)) close(); });
        window.addEventListener('resize', close);
        document.addEventListener('scroll', event => { if (!list.contains(event.target)) close(); }, true);
    }
    window.ITMAutocomplete = {init};
    const start = () => (window.itmFiltrosAutocompletado || []).forEach(init);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true}); else start();
})();
