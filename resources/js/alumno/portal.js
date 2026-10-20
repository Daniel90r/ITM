import '../../css/alumno/portal.css';

const portal = document.querySelector('.portal-alumno');
if (portal) {
    const menu = portal.querySelector('#portal-menu');
    const toggle = portal.querySelector('.portal-menu-toggle');
    const close = portal.querySelector('.portal-menu-close');
    const backdrop = portal.querySelector('.portal-menu-backdrop');
    const main = portal.querySelector('.portal-main');
    const mobile = window.matchMedia('(max-width: 1023px)');
    let open = false;
    const controls = () => [...menu.querySelectorAll('a[href],button:not([disabled])')]
        .filter(element => element.getClientRects().length);

    function setOpen(value, restoreFocus = true) {
        open = value && mobile.matches;
        portal.classList.toggle('portal-menu-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú del Portal Estudiantil' : 'Abrir menú del Portal Estudiantil');
        backdrop.hidden = !open;
        menu.inert = mobile.matches && !open;
        main.inert = open;
        if (open) controls()[0]?.focus();
        else if (restoreFocus && mobile.matches) toggle.focus();
    }
    toggle.addEventListener('click', () => setOpen(!open));
    close.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    menu.addEventListener('click', event => {
        if (event.target.closest('a, .sidebar-btn-logout')) setOpen(false, false);
    });
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key === 'Tab') {
            const items = controls();
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    mobile.addEventListener('change', () => setOpen(false, false));
    setOpen(false, false);

    // Ajustes exclusivos del portal para el modal compartido; sin cambiar sus acciones.
    const logout = document.querySelector('#logoutModal');
    logout?.classList.add('portal-logout-modal');
    logout?.setAttribute('role', 'dialog');
    logout?.setAttribute('aria-modal', 'true');
    logout?.setAttribute('aria-label', 'Cerrar sesión');
    if (logout) {
        let previousFocus;
        new MutationObserver(() => {
            const visible = !logout.classList.contains('hidden');
            main.inert = visible || open;
            menu.inert = visible || (mobile.matches && !open);
            if (visible) {
                previousFocus = document.activeElement;
                logout.querySelector('#logoutCancel')?.focus();
            } else {
                (mobile.matches ? toggle : previousFocus)?.focus();
            }
        }).observe(logout, { attributes: true, attributeFilter: ['class'] });
        document.addEventListener('keydown', event => {
            if (logout.classList.contains('hidden') || event.key !== 'Tab') return;
            const buttons = [...logout.querySelectorAll('button')];
            if (event.shiftKey && document.activeElement === buttons[0]) {
                event.preventDefault(); buttons.at(-1)?.focus();
            } else if (!event.shiftKey && document.activeElement === buttons.at(-1)) {
                event.preventDefault(); buttons[0]?.focus();
            }
        });
    }
}
