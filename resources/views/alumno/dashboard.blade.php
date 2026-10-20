<x-app-layout>

<div id="portal-alumno" class="page-layout fade-in alumnos portal-alumno">

    @include('components.alumno-sidebar', ['active' => 'inicio'])

    <div class="main-content portal-main">

        <header class="page-header portal-header">
            @include('alumno.partials.menu-toggle')
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM Aguilares" class="sidebar-brand-img">
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Portal Estudiantil</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $alumno->nombre_completo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="portal-content content-body">

            @if(session('success'))
            <div class="alert-success">
                ✓ {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="alert-error">
                ✕ {{ session('error') }}
            </div>
            @endif

            <div class="banner-warning">
                <div>
                    <h3 style="font-size:16px; font-weight:700; color:#b45309; margin:0 0 4px;">Bienvenido, {{ $alumno->nombre }}</h3>
                     <p style="font-size:13px; color:#92400e; margin:0;">Ciclo escolar {{ now()->year }} · I.T.M. Aguilares</p>
                </div>
                <div style="display:flex; gap:12px;">
                    <div class="banner-stat banner-stat-warning">
                        <p style="font-size:22px; font-weight:700; color:#d97706; margin:0;">{{ $inscripcion ? 'Sec. '.$inscripcion->curso->seccion : '—' }}</p>
                        <p style="font-size:11px; color:#92400e; margin:0;">Mi sección</p>
                    </div>
                    <div class="banner-stat banner-stat-warning">
                        <p style="font-size:22px; font-weight:700; color:#d97706; margin:0;">{{ $promedio ? round($promedio) : '—' }}</p>
                        <p style="font-size:11px; color:#92400e; margin:0;">Promedio</p>
                    </div>
                </div>
            </div>

            <div class="card-grid-3">
                <div class="stat-card">
                    <div class="stat-icon-box icon-box-amber">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1 2 2H6a2 2 0 0 1 2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                    </div>
                    <p style="font-size:24px; font-weight:700; color:#1f2937; margin:0 0 4px;">{{ $inscripcion ? 'Inscrito' : 'Sin sección' }}</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Estado de inscripción</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-box-amber">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 0 0 0-2 2v16a2 0 0 0 2 2h12a2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <p style="font-size:24px; font-weight:700; color:#1f2937; margin:0 0 4px;">{{ $notas->count() }}</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Notas registradas</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-box-amber">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                    <p style="font-size:24px; font-weight:700; color:#1f2937; margin:0 0 4px;">—</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Pagos pendientes</p>
                </div>
            </div>

            @include('alumno.partials.mi-curso')

            @if($notas->count() > 0)
            <div class="card">
                <div class="card-header">
                    <p style="font-size:14px; font-weight:600; color:#1f2937; margin:0;">Últimas notas</p>
                    <a href="{{ route('alumno.notas') }}" class="btn-link" style="color:#d97706;">Ver todas</a>
                </div>
                @foreach($notas->take(5) as $nota)
                <div class="portal-nota-reciente" style="display:flex; align-items:center; padding:11px 18px; border-top:1px solid #f3f4f6;">
                    <p style="font-size:13px; color:#374151; flex:1; margin:0;">{{ $nota->detalleCurso->materia->nombre }}</p>
                    <p style="font-size:11px; color:#6b7280; margin:0 16px;">Docente: {{ $miCurso['materias']->firstWhere('detalle_id', $nota->detalle_curso_id)['docente'] ?? 'No disponible' }}</p>
                    @php $p = $nota->promedio; @endphp
                    <span class="prom-badge"
                          style="background:{{ $p >= 9 ? '#059669' : ($p >= 7 ? '#d97706' : ($p >= 6 ? '#ea580c' : '#dc2626')) }}; color:#fff;">
                        {{ $p ?? '—' }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif

        </div>
    </div>
</div>
</x-app-layout>
