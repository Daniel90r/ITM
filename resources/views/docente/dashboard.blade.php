<x-app-layout>
{{-- ═══════════════════════════════════════════════════════════════════════════════════════════════ --}}
{{-- VISTA: DOCENTE - DASHBOARD --}}
{{-- ═══════════════════════════════════════════════════════════════════════════════════════════════ --}}

<div class="page-layout fade-in">

    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    {{-- DOCENTE: SIDEBAR --}}
    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    <aside id="staff-navigation" data-staff-sidebar aria-label="Docente" class="sidebar sidebar-scroll">
    @include('components.staff-menu-close')

        <div class="sidebar-brand">
            <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM Aguilares" class="sidebar-brand-img">
            <div>
                <p style="font-weight:700; font-size:14px; color:#1f2937;">ITM Aguilares</p>
                <p style="font-size:11px; color:#6b7280;">Sistema Académico</p>
            </div>
        </div>

        <p class="sidebar-section-label" style="color:#9ca3af;">Académico</p>
        <a href="{{ route('docente.dashboard') }}" class="sidebar-link sidebar-link-active">
            <x-icono nombre="panel" />
            Panel principal
        </a>

        <a href="{{ route('docente.notas') }}" class="sidebar-link">
            <x-icono nombre="notas" />
            Registro de notas
        </a>

        <a href="{{ route('docente.asistencia') }}" class="sidebar-link">
            <x-icono nombre="asistencias" />
            Asistencia
        </a>

        <div class="sidebar-user">
            <div class="sidebar-avatar sidebar-avatar-blue">
                {{ strtoupper(substr($docente->nombre,0,1).substr($docente->apellido,0,1)) }}
            </div>
            <div style="flex:1; overflow:hidden;">
                <p style="font-size:13px; font-weight:500; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $docente->nombre_completo }}</p>
                <p style="font-size:11px; color:#6b7280;">Docente</p>
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

    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    {{-- DOCENTE: CONTENIDO PRINCIPAL --}}
    {{-- ───────────────────────────────────────────────────────────────────────────────────────── --}}
    <div class="main-content">

        <header class="page-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="icon-box" style="background:#3b82f6;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Mis Cursos</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Programas activos asignados</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="content-body">

            {{-- PASO 3: RESUMEN SUPERIOR --}}
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px;">

                <div class="stat-card-white" style="flex:1; min-width:200px;">
                    <p style="font-size:11px; text-transform:uppercase; color:#6b7280; margin:0;">Docente</p>
                    <p style="font-size:16px; font-weight:700; color:#111827; margin:4px 0 0 0;">{{ $docente->nombre_completo }}</p>
                    <p style="font-size:12px; color:#6b7280; margin:2px 0 0 0;">Código: {{ $docente->codigo }}</p>
                </div>

                <div class="stat-card-white" style="flex:1; min-width:200px;">
                    <p style="font-size:11px; text-transform:uppercase; color:#6b7280; margin:0;">Cursos asignados</p>
                    @php($totalCursos = $cursos->count())
                    <p style="font-size:16px; font-weight:700; color:#111827; margin:4px 0 0 0;">{{ $totalCursos }} {{ $totalCursos === 1 ? 'curso' : 'cursos' }}</p>
                </div>

                <div class="stat-card-white" style="flex:1; min-width:200px;">
                    <p style="font-size:11px; text-transform:uppercase; color:#6b7280; margin:0;">Programas activos</p>
                    @php($totalProgramas = $cursos->flatten(1)->count())
                    <p style="font-size:16px; font-weight:700; color:#111827; margin:4px 0 0 0;">{{ $totalProgramas }} {{ $totalProgramas === 1 ? 'programa' : 'programas' }}</p>
                </div>

            </div>

            {{-- PASO 4-6: TARJETAS POR CURSO --}}
            <div class="card-grid">

                @forelse($cursos as $detalles)
                    @php($curso = $detalles->first()->curso)
                    <div data-course-id="{{ $curso->id }}" class="card" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                        <div class="card-header" style="padding:14px 18px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:#f9fafb;">
                            <div>
                                <h3 style="font-size:15px; font-weight:600; color:#111827; margin:0;">{{ $curso->nombre }}</h3>
                                <p style="font-size:12px; color:#6b7280; margin:2px 0 0 0;">{{ $curso->nivel }} • Sección {{ $curso->seccion }} • Año {{ $curso->anio_lectivo }}</p>
                            </div>
                            <span class="badge-green">Activo</span>
                        </div>
                        <div class="card-body" style="padding:14px 18px;">
                            @php($totalProgramasCurso = $detalles->count())
                            <p style="font-size:11px; color:#6b7280; margin:0 0 10px 0; text-transform:uppercase; letter-spacing:.05em;">{{ $totalProgramasCurso }} {{ $totalProgramasCurso === 1 ? 'programa' : 'programas' }}</p>
                            @foreach($detalles as $detalle)
                                <div data-detail-id="{{ $detalle->id }}" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; padding:10px 0; border-top:1px solid #f1f5f9;">
                                    <div>
                                        <p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $detalle->materia->nombre }}</p>
                                        <p style="font-size:11px; color:#6b7280; margin:0;">{{ $detalle->materia->codigo }}</p>
                                    </div>
                                    <a href="{{ route('docente.notas', ['detalle_curso_id' => $detalle->id]) }}"
                                       style="display:inline-block; padding:5px 12px; background:#3b82f6; color:#fff; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">
                                        Registrar notas
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <div class="empty-icon-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <p style="font-size:14px; color:#6b7280; margin:0;">No tienes programas activos asignados actualmente.</p>
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>
<x-logout-modal />
</x-app-layout>
