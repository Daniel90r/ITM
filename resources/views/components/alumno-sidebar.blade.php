@props(['alumno' => auth()->user(), 'active' => ''])

{{-- ═══════════════════════════════════════════════════════════════════════════════════════════════ --}}
{{-- COMPONENTE: SIDEBAR ALUMNO --}}
{{-- ═══════════════════════════════════════════════════════════════════════════════════════════════ --}}

@once
    @push('styles')
        @vite('resources/js/alumno/portal.js')
    @endpush
@endonce
<button class="portal-menu-backdrop" type="button" aria-label="Cerrar menú" tabindex="-1" hidden></button>
<aside id="portal-menu" aria-label="Navegación del alumno" class="sidebar portal-sidebar {{ $active === 'pagos' || $active === 'inscripcion' ? 'sidebar-scroll' : '' }}">
    <button type="button" class="portal-menu-close" aria-label="Cerrar menú">Cerrar <span aria-hidden="true">×</span></button>

    {{-- Logo e Institución --}}
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM Aguilares" class="sidebar-brand-img">
        <div>
            <p style="font-weight:700; font-size:14px; color:#fff;">ITM Aguilares</p>
            <p style="font-size:11px; color:#64748b;">Portal Estudiantil</p>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    {{-- Sección: Mi Portal --}}
    {{-- ───────────────────────────────────────────────────────────────────────────────────────────────────────── --}}
    <p class="sidebar-section-label" style="color:#9ca3af;">Mi portal</p>

    {{-- Enlace: Inicio --}}
    <a href="{{ route('alumno.dashboard') }}"
       @if($active === 'inicio') aria-current="page" @endif
       class="sidebar-link {{ $active === 'inicio' ? 'sidebar-link-active' : '' }}"
       style="{{ $active === 'inicio' ? 'background:rgba(245,158,11,.1); color:#d97706;' : 'background:transparent; color:#6b7280;' }}">
        <x-icono nombre="panel" />
        Inicio
    </a>

    {{-- Enlace: Inscripción --}}
    <a href="{{ route('alumno.inscripcion') }}"
       @if($active === 'inscripcion') aria-current="page" @endif
       class="sidebar-link"
       style="{{ $active === 'inscripcion' ? 'background:rgba(245,158,11,.1); color:#d97706; font-weight:600;' : 'background:transparent; color:#6b7280; font-weight:400;' }}">
        <x-icono nombre="inscripciones" />
        Inscripción
    </a>

    {{-- Enlace: Mis Notas --}}
    <a href="{{ route('alumno.notas') }}"
       @if($active === 'notas') aria-current="page" @endif
       class="sidebar-link"
       style="{{ $active === 'notas' ? 'background:rgba(245,158,11,.1); color:#d97706; font-weight:600;' : 'background:transparent; color:#6b7280; font-weight:400;' }}">
        <x-icono nombre="boletas" />
        Mis notas
    </a>

    {{-- Enlace: Estado de Pagos --}}
    <a href="{{ route('alumno.pagos') }}"
       @if($active === 'pagos') aria-current="page" @endif
       class="sidebar-link"
       style="{{ $active === 'pagos' ? 'background:rgba(245,158,11,.1); color:#d97706; font-weight:600;' : 'background:transparent; color:#6b7280; font-weight:400;' }}">
        <x-icono nombre="mensualidades" />
        Estado de pagos
    </a>

    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    {{-- Usuario Info --}}
    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    <div class="sidebar-user">
        <div class="sidebar-avatar sidebar-avatar-amber">
            {{ strtoupper(substr($alumno->nombre ?? 'U', 0, 1) . substr($alumno->apellido ?? '', 0, 1)) }}
        </div>
        <div style="flex:1; overflow:hidden;">
            <p style="font-size:13px; font-weight:500; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                {{ $alumno->nombre_completo ?? $alumno->name ?? '' }}
            </p>
            <p style="font-size:11px; color:#6b7280;">{{ $alumno->codigo ?? '' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="button" title="Salir" class="sidebar-btn-logout"
                    onclick="openLogoutModal(this.closest('form'))">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </form>
    </div>
</aside>
<x-logout-modal />
