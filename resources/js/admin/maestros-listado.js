/* Interacción del menú de acciones; no modifica formularios ni asignaciones. */
(() => {
    function init() {
        let current = null;
        const close = (restoreFocus = false) => {
            if (!current) return;
            const { menu, trigger } = current;
            current = null;
            if (typeof menu.hidePopover === 'function' && menu.matches(':popover-open')) menu.hidePopover();
            menu.removeAttribute('data-gm-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        };
        document.querySelectorAll('.gestion-maestros [data-gm-trigger]').forEach(trigger => {
            const menu = document.getElementById(trigger.getAttribute('aria-controls'));
            if (!menu) return;
            const items = [...menu.querySelectorAll('[role="menuitem"]')];
            const open = (last = false) => {
                close();
                current = { menu, trigger };
                menu.setAttribute('data-gm-open', '');
                if (typeof menu.showPopover === 'function') menu.showPopover();
                else document.body.append(menu); // Compatibilidad sin Popover API.
                const rect = trigger.getBoundingClientRect();
                const width = menu.offsetWidth, height = menu.offsetHeight;
                menu.style.left = `${Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12))}px`;
                menu.style.top = `${Math.max(12, rect.bottom + height + 8 <= window.innerHeight ? rect.bottom + 6 : rect.top - height - 6)}px`;
                trigger.setAttribute('aria-expanded', 'true');
                items[last ? items.length - 1 : 0]?.focus();
            };
            trigger.addEventListener('click', () => current?.trigger === trigger ? close(true) : open());
            trigger.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault(); open(event.key === 'ArrowUp');
                }
            });
            menu.addEventListener('keydown', event => {
                const index = items.indexOf(document.activeElement);
                if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault();
                    const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                    items[next]?.focus();
                }
                if (event.key === 'Escape') { event.preventDefault(); close(true); }
            });
            menu.addEventListener('click', event => { if (event.target.closest('[role="menuitem"]')) close(); });
            menu.addEventListener('focusout', event => {
                if (event.relatedTarget && !menu.contains(event.relatedTarget) && event.relatedTarget !== trigger) close();
            });
            menu.addEventListener('toggle', event => {
                if (event.newState === 'closed' && current?.menu === menu) close();
            });
        });
        document.addEventListener('click', event => {
            if (current && !current.menu.contains(event.target) && !current.trigger.contains(event.target)) close();
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && current) close(true); });
        window.addEventListener('resize', () => close());
        document.addEventListener('scroll', event => { if (current && !current.menu.contains(event.target)) close(); }, true);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
