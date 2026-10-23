<div class="staff-mobile-bar">
    <button type="button" id="staff-menu-toggle" aria-controls="staff-navigation" aria-expanded="false" aria-label="Abrir menú de navegación">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <img src="{{ asset('images/logo_itm.jpg') }}" alt="Logotipo del ITM" width="36" height="36">
    <div><strong>ITM Aguilares</strong><span>{{ auth()->user()->role === 'admin' ? 'Administración' : 'Docente' }}</span></div>
</div>
<button type="button" id="staff-menu-backdrop" class="staff-menu-backdrop" aria-label="Cerrar menú de navegación" tabindex="-1" hidden></button>
