<x-app-layout>

<div id="portal-alumno" class="page-layout fade-in alumnos portal-alumno">

    @include('components.alumno-sidebar', ['active' => 'notas'])

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

        <div class="portal-content content-body fade-in">

            <div class="card">
                <div class="card-header">
                    <div>
                        <p class="section-title" style="margin:0;">Mis notas</p>
                        @if($inscripcion)
                        <p style="font-size:12px; color:#6b7280; margin:3px 0 0;">Sección {{ $inscripcion->curso->seccion }} · Turno {{ $inscripcion->curso->nivel }}</p>
                        @endif
                    </div>
                </div>

                @if($notas->count() > 0)
                <p class="portal-table-hint">Desliza la tabla para consultar todas las calificaciones.</p>
                <div class="portal-table-scroll" role="region" aria-label="Calificaciones por materia" tabindex="0" style="overflow-x:auto;">
                    <table class="data-table portal-notas-table">
                        <thead>
                            <tr>
                                <th style="padding:10px 16px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Materia</th>
                                <th style="padding:10px 12px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Docente</th>
                                <th style="padding:10px 8px; text-align:center; color:#8b5cf6; font-weight:600; font-size:11px; text-transform:uppercase; width:90px;">Laboratorio</th>
                                <th style="padding:10px 8px; text-align:center; color:#f59e0b; font-weight:600; font-size:11px; text-transform:uppercase; width:90px;">Ex. Teórico</th>
                                <th style="padding:10px 8px; text-align:center; color:#10b981; font-weight:600; font-size:11px; text-transform:uppercase; width:90px;">Práctica</th>
                                <th style="padding:10px 8px; text-align:center; color:#1f2937; font-weight:700; font-size:11px; text-transform:uppercase; width:80px;">Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notas as $nota)
                            @php $p = $nota->promedio; @endphp
                            <tr class="table-row">
                                <td style="padding:12px 16px; color:#1f2937; font-weight:500;">{{ $nota->detalleCurso->materia->nombre }}</td>
                                <td style="padding:12px 12px; color:#6b7280; font-size:12px;">{{ $nota->detalleCurso->maestro->nombre_completo }}</td>
                                <td style="padding:12px 8px; text-align:center; color:#8b5cf6;">{{ $nota->laboratorio ?? '—' }}</td>
                                <td style="padding:12px 8px; text-align:center; color:#f59e0b;">{{ $nota->examen_teorico ?? '—' }}</td>
                                <td style="padding:12px 8px; text-align:center; color:#10b981;">{{ $nota->practica ?? '—' }}</td>
                                <td style="padding:12px 8px; text-align:center;">
                                    @if($p !== null)
                                    <span class="prom-badge"
                                          style="background:{{ $p >= 9 ? '#10b981' : ($p >= 7 ? '#f59e0b' : ($p >= 6 ? '#f97316' : '#ef4444')) }}; color:#fff;">
                                        {{ $p }}
                                    </span>
                                    @else
                                    <span style="color:#d1d5db;">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @elseif($inscripcion)
                <div class="card-body" style="padding:40px; text-align:center;">
                    <p style="color:#6b7280; font-size:13px;">Aún no tienes notas registradas.</p>
                </div>
                @else
                <div class="card-body" style="padding:40px; text-align:center;">
                    <p style="color:#6b7280; font-size:13px;">Debes inscribirte a una sección primero.</p>
                </div>
                @endif
            </div>

        </div>
    </div>
</div>
</x-app-layout>
