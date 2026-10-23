/* Adaptación de presentación; no envía formularios ni modifica valores. */
(() => {
    function init() {
        const sidebar = document.querySelector('[data-staff-sidebar]');
        const toggle = document.getElementById('staff-menu-toggle');
        const backdrop = document.getElementById('staff-menu-backdrop');
        if (!sidebar || !toggle || !backdrop || !document.body.classList.contains('itm-staff')) return;
        const holder = sidebar.parentElement.tagName === 'DETAILS' ? sidebar.parentElement : sidebar;
        if (holder !== sidebar) { holder.open = true; holder.setAttribute('data-staff-holder', ''); }
        const shell = holder.parentElement;
        shell.setAttribute('data-staff-shell', '');
        const panels = [...shell.children].filter(el => el !== holder && !['SCRIPT', 'STYLE'].includes(el.tagName) && getComputedStyle(el).position !== 'fixed');
        panels.forEach(el => el.setAttribute('data-staff-panel', ''));
        const media = window.matchMedia('(max-width:1023px)');
        const close = sidebar.querySelector('.staff-menu-close');
        let opened = false;
        function setOpen(value, restore = true) {
            opened = Boolean(value && media.matches);
            document.body.toggleAttribute('data-staff-menu-open', opened);
            toggle.setAttribute('aria-expanded', String(opened));
            toggle.setAttribute('aria-label', opened ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
            backdrop.hidden = !opened;
            sidebar.inert = media.matches && !opened;
            panels.forEach(el => { el.inert = opened; });
            if (opened) { sidebar.scrollTop = 0; (close || sidebar.querySelector('a')).focus(); }
            else if (restore && media.matches) toggle.focus();
        }
        toggle.addEventListener('click', () => setOpen(!opened));
        backdrop.addEventListener('click', () => setOpen(false));
        close?.addEventListener('click', () => setOpen(false));
        sidebar.addEventListener('click', event => {
            if (event.target.closest('a[href],button:not(.staff-menu-close)')) setOpen(false, false);
        });
        document.addEventListener('keydown', event => {
            if (!opened) return;
            if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
            if (event.key !== 'Tab') return;
            const items = [toggle, ...sidebar.querySelectorAll('a[href],button:not([disabled]),input:not([type=hidden]),select,[tabindex="0"]')].filter(el => el.getClientRects().length && !el.disabled);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        media.addEventListener('change', () => setOpen(false, false));
        function decorate(root) {
            const elements = root.querySelectorAll('div,section,form,header');
            elements.forEach(el => {
                if (sidebar.contains(el) || el === shell) return;
                const style = getComputedStyle(el);
                if (style.display === 'grid') el.classList.add('staff-grid');
                if (style.display === 'flex' && style.flexDirection === 'row') {
                    el.classList.add('staff-flex-row');
                    if (el.querySelector('input:not([type=checkbox]):not([type=radio]),select,textarea') && !el.querySelector('table')) el.classList.add('staff-field-row');
                }
                if (style.position === 'fixed' && (/modal/i.test(el.id) || el.getAttribute('role') === 'dialog')) el.setAttribute('data-staff-modal', '');
            });
            root.querySelectorAll('table').forEach(table => {
                if (table.closest('.staff-table-scroll')) return;
                let region = table.parentElement;
                if (!['auto', 'scroll'].includes(getComputedStyle(region).overflowX)) {
                    region = document.createElement('div');
                    table.before(region); region.append(table);
                }
                region.classList.add('staff-table-scroll');
                region.setAttribute('role', 'region');
                region.setAttribute('aria-label', table.caption?.textContent.trim() || 'Tabla con desplazamiento horizontal');
                if (!region.hasAttribute('tabindex')) region.tabIndex = 0;
            });
        }
        decorate(document.body);
        const observer = new MutationObserver(records => {
            if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && !node.classList.contains('staff-table-scroll')))) decorate(document.body);
        });
        observer.observe(document.body, { childList: true, subtree: true });
        document.body.setAttribute('data-staff-ready', '');
        setOpen(false, false);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
