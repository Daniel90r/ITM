@props(['maestro' => \App\Models\Maestro::where('user_id', auth()->id())->first(), 'active' => ''])

<aside id="staff-navigation" data-staff-sidebar aria-label="Docente" style="width:220px; flex-shrink:0; background:#ffffff; border-right:1px solid #e5e7eb; display:flex; flex-direction:column; padding:20px 12px; gap:2px;">
    @include('components.staff-menu-close')

    {{-- Logo --}}
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #e5e7eb;">
        <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:38px; height:38px; border-radius:10px; flex-shrink:0; object-fit:cover;">
        <div>
            <p style="font-weight:700; font-size:14px; color:#1f2937; margin:0;">ITM Aguilares</p>
            <p style="font-size:11px; color:#6b7280; margin:0;">Sistema Académico</p>
        </div>
    </div>

    {{-- ACADÉMICO --}}
    <p style="font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.1em; font-weight:600; padding:10px 8px 3px; margin:0;">ACADÉMICO</p>

    <a href="{{ route('docente.notas') }}" class="sidebar-link {{ $active === 'notas' ? 'active' : '' }}">
        <x-icono nombre="notas" />
        Registro de notas
    </a>

    <a href="{{ route('docente.asistencia') }}" class="sidebar-link {{ $active === 'asistencia' ? 'active' : '' }}">
        <x-icono nombre="asistencias" />
        Asistencia
    </a>

    {{-- Usuario --}}
    <div style="margin-top:auto; padding-top:14px; border-top:1px solid #e5e7eb; display:flex; align-items:center; gap:10px;">
        <div style="width:32px; height:32px; border-radius:50%; background:#3b82f6; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:700; flex-shrink:0;">
            {{ strtoupper(substr($maestro->nombre ?? 'U',0,1).substr($maestro->apellido ?? 'U',0,1)) }}
        </div>
        <div style="flex:1; overflow:hidden;">
            <p style="font-size:13px; font-weight:500; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;">{{ $maestro->nombre_completo ?? 'Usuario' }}</p>
            <p style="font-size:11px; color:#6b7280; margin:0;">Docente</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="button" title="Salir" style="background:none; border:none; cursor:pointer; color:#6b7280; padding:4px;"
                onclick="openLogoutModal(this.closest('form'))"
                onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </form>
    </div>
</aside>
<x-logout-modal />
