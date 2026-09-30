@props(['active' => '', 'solicitudesPendientes' => 0])

{{-- El conteo lo entrega AppServiceProvider (View::composer) o la propia vista
     que ya lo calculó. Esta vista NUNCA consulta la base de datos. --}}
@php($solicitudesPendientes = $solicitudesPendientes ?? 0)

<aside style="width:220px; flex-shrink:0; background:#ffffff; border-right:1px solid #e5e7eb; display:flex; flex-direction:column; padding:20px 12px; gap:2px; overflow-y:auto;">

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
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Panel principal
    </a>

    {{-- GESTIÓN --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">GESTIÓN</p>

    <a href="{{ route('admin.alumnos') }}" class="sidebar-link {{ $active === 'alumnos' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Alumnos
    </a>

    <a href="{{ route('admin.maestros') }}" class="sidebar-link {{ $active === 'maestros' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-0 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Maestros
    </a>

    {{-- ACADÉMICO --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">ACADÉMICO</p>

    <a href="{{ route('admin.materias') }}" class="sidebar-link {{ $active === 'materias' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6.5"/></svg>
        Materias
    </a>

    <a href="{{ route('admin.cursos') }}" class="sidebar-link {{ $active === 'cursos' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Cursos
    </a>

    <a href="{{ route('admin.solicitudes') }}" class="sidebar-link {{ $active === 'solicitudes' ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
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
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
        Alumnos inscritos
    </a>

    <a href="{{ route('admin.asistencias') }}" class="sidebar-link {{ $active === 'asistencias' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-0 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
        Asistencias
    </a>

    <a href="{{ route('admin.notas') }}" class="sidebar-link {{ $active === 'notas' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Notas
    </a>

    <a href="{{ route('admin.boletas') }}" class="sidebar-link {{ $active === 'boletas' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Boletas
    </a>

    {{-- FINANZAS --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">FINANZAS</p>

    <a href="{{ route('admin.mensualidades') }}" class="sidebar-link {{ $active === 'mensualidades' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Mensualidades
    </a>

    <a href="{{ route('admin.pagos') }}" class="sidebar-link {{ $active === 'pagos' ? 'active' : '' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Pagos
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