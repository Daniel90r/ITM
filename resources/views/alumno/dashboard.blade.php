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
                        <x-icono nombre="inscripciones" tamano="16" stroke="#3b82f6" />
                    </div>
                    <p style="font-size:24px; font-weight:700; color:#1f2937; margin:0 0 4px;">{{ $inscripcion ? 'Inscrito' : 'Sin sección' }}</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Estado de inscripción</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-box-amber">
                        <x-icono nombre="boletas" tamano="16" stroke="#f59e0b" />
                    </div>
                    <p style="font-size:24px; font-weight:700; color:#1f2937; margin:0 0 4px;">{{ $notas->count() }}</p>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Notas registradas</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-box-amber">
                        <x-icono nombre="mensualidades" tamano="16" stroke="#f59e0b" />
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
