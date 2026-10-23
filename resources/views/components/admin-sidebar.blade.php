@props(['active' => '', 'solicitudesPendientes' => 0])

{{-- El conteo lo entrega AppServiceProvider (View::composer) o la propia vista
     que ya lo calculó. Esta vista NUNCA consulta la base de datos. --}}
@php($solicitudesPendientes = $solicitudesPendientes ?? 0)

<aside id="staff-navigation" data-staff-sidebar aria-label="Administración" style="width:220px; flex-shrink:0; background:#ffffff; border-right:1px solid #e5e7eb; display:flex; flex-direction:column; padding:20px 12px; gap:2px; overflow-y:auto;">
    @include('components.staff-menu-close')

    {{-- Logo --}}
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #e5e7eb; flex-shrink:0;">
        <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:38px; height:38px; border-radius:10px; flex-shrink:0; object-fit:cover;">
        <div>
            <p style="font-weight:700; font-size:14px; color:#111827; margin:0;">ITM Aguilares</p>
            <p style="font-size:11px; color:#6b7280; margin:0;">Sistema Académico</p>
        </div>
    </div>

    {{-- PRINCIPAL --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">PRINCIPAL</p>

    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ $active === 'dashboard' ? 'active' : '' }}">
        <x-icono nombre="panel" />
        Panel principal
    </a>

    {{-- GESTIÓN --}}
    <a href="{{ route('admin.reportes') }}" class="sidebar-link {{ $active === 'reportes' ? 'active' : '' }}">
        <x-icono nombre="reportes" />
        Reportes
    </a>
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">GESTIÓN</p>

    <a href="{{ route('admin.alumnos') }}" class="sidebar-link {{ $active === 'alumnos' ? 'active' : '' }}">
        <x-icono nombre="alumnos" />
        Alumnos
    </a>

    <a href="{{ route('admin.maestros') }}" class="sidebar-link {{ $active === 'maestros' ? 'active' : '' }}">
        <x-icono nombre="maestros" />
        Maestros
    </a>

    <a href="{{ route('admin.directores.index') }}" class="sidebar-link {{ $active === 'directores' ? 'active' : '' }}">
        <x-icono nombre="directores" />
        Directores
    </a>

    {{-- ACADÉMICO --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">ACADÉMICO</p>

    <a href="{{ route('admin.materias') }}" class="sidebar-link {{ $active === 'materias' ? 'active' : '' }}">
        <x-icono nombre="materias" />
        Materias
    </a>

    <a href="{{ route('admin.cursos') }}" class="sidebar-link {{ $active === 'cursos' ? 'active' : '' }}">
        <x-icono nombre="cursos" />
        Cursos
    </a>

    <a href="{{ route('admin.solicitudes') }}" class="sidebar-link {{ $active === 'solicitudes' ? 'active' : '' }}">
    <x-icono nombre="solicitudes" />
    <span>Solicitudes</span>
    <span
        data-solicitudes-badge
        data-url="{{ route('admin.solicitudes.pendientesCount') }}"
        data-count="{{ $solicitudesPendientes }}"
        title="{{ $solicitudesPendientes }} solicitud(es) pendiente(s)"
        aria-label="{{ $solicitudesPendientes }} solicitud(es) pendiente(s)"
        style="display:{{ $solicitudesPendientes > 0 ? 'inline-flex' : 'none' }}; margin-left:auto; min-width:18px; height:18px; padding:0 5px; border-radius:9px; background:#ef4444; color:#ffffff; font-size:10px; font-weight:700; align-items:center; justify-content:center; flex-shrink:0;"
    >{{ $solicitudesPendientes }}</span>
    </a>

    <a href="{{ route('admin.inscripciones') }}" class="sidebar-link {{ $active === 'inscripciones' ? 'active' : '' }}">
        <x-icono nombre="inscripciones" />
        Alumnos inscritos
    </a>

    <a href="{{ route('admin.asistencias') }}" class="sidebar-link {{ $active === 'asistencias' ? 'active' : '' }}">
        <x-icono nombre="asistencias" />
        Asistencias
    </a>

    <a href="{{ route('admin.notas') }}" class="sidebar-link {{ $active === 'notas' ? 'active' : '' }}">
        <x-icono nombre="notas" />
        Notas
    </a>

    <a href="{{ route('admin.boletas') }}" class="sidebar-link {{ $active === 'boletas' ? 'active' : '' }}">
        <x-icono nombre="boletas" />
        Boletas
    </a>

    {{-- FINANZAS --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">FINANZAS</p>

    <a href="{{ route('admin.mensualidades') }}" class="sidebar-link {{ $active === 'mensualidades' ? 'active' : '' }}">
        <x-icono nombre="mensualidades" />
        Mensualidades
    </a>

    <a href="{{ route('admin.pagos') }}" class="sidebar-link {{ $active === 'pagos' ? 'active' : '' }}">
        <x-icono nombre="pagos" />
        Pagos
    </a>

    {{-- SISTEMA --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">SISTEMA</p>

    <a href="{{ route('admin.respaldos') }}" class="sidebar-link {{ $active === 'respaldos' ? 'active' : '' }}">
        <x-icono nombre="respaldos" />
        Copias de seguridad
    </a>

    {{-- Usuario Info --}}
    <div style="margin-top:auto; padding-top:14px; border-top:1px solid #e5e7eb; display:flex; align-items:center; gap:10px; flex-shrink:0;">
        <div style="width:32px; height:32px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
            ADM
        </div>
        <div style="flex:1; overflow:hidden;">
            <p style="font-size:13px; font-weight:500; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;">Administrador</p>
            <p style="font-size:11px; color:#6b7280; margin:0;">Sistema</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="button" title="Salir" style="background:none; border:none; cursor:pointer; color:#9ca3af; padding:4px;"
                onclick="openLogoutModal(this.closest('form'))"
                onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#9ca3af'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </form>
    </div>
</aside>
<x-logout-modal />
