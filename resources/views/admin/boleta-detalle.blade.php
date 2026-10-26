<x-app-layout>
@push('styles')
<style>
    .boleta-layout { display:flex; height:100vh; overflow:hidden; }
    .boleta-layout .boleta-main { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc; }
    .boleta-layout .boleta-header { display:flex; flex-wrap:wrap; align-items:center; gap:16px; padding:18px 24px; background:#fff; border-bottom:1px solid #e5e7eb; }
    .boleta-layout .boleta-heading { display:flex; flex-wrap:wrap; align-items:center; gap:12px; min-width:0; }
    .boleta-layout .boleta-title { margin:0; font-size:18px; font-weight:700; color:#111827; }
    .boleta-layout .boleta-subtitle { margin:4px 0 0; font-size:12px; color:#6b7280; }
    .boleta-layout .boleta-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-left:auto; }
    .boleta-layout .boleta-actions form { margin:0; display:flex; }
    .boleta-layout .boleta-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; padding:10px 16px; box-sizing:border-box; border:1px solid transparent; border-radius:8px; font-family:inherit; font-size:12px; font-weight:600; line-height:1.5; text-decoration:none; white-space:nowrap; cursor:pointer; }
    .boleta-layout .boleta-emitir { color:#fff; background:#15803d; }
    .boleta-layout .boleta-emitir:hover { background:#166534; }
    .boleta-layout .boleta-pdf-link { color:#fff; background:#111827; }
    .boleta-layout .boleta-pdf-link:hover { background:#374151; }
    .boleta-layout .boleta-btn.boleta-inactiva { background:#f3f4f6; color:#6b7280; border-color:#d1d5db; cursor:not-allowed; }
    .boleta-layout .boleta-emitida { background:#f0fdf4; color:#166534; border-color:#bbf7d0; cursor:default; }
    .boleta-layout :is(a,button,select):focus-visible { outline:2px solid #2563eb; outline-offset:3px; }
    .boleta-layout .boleta-content { flex:1; min-height:0; overflow-y:auto; padding:24px; container:boleta-preview / inline-size; }
    .boleta-layout .boleta-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; margin-bottom:20px; }
    .boleta-layout .boleta-info-card { display:grid; grid-template-columns:minmax(0,1fr) 220px; gap:24px; align-items:stretch; }
    .boleta-layout .boleta-student { display:flex; align-items:flex-start; gap:14px; min-width:0; }
    .boleta-layout .boleta-avatar { width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#111827; color:#fff; font-size:15px; font-weight:700; }
    .boleta-layout .boleta-student-info { min-width:0; flex:1; }
    .boleta-layout .boleta-student-name { margin:0 0 16px; font-size:20px; font-weight:700; line-height:1.4; color:#111827; overflow-wrap:anywhere; }
    .boleta-layout .boleta-meta { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:16px; margin:0; }
    .boleta-layout .boleta-meta dt { font-size:11px; color:#6b7280; margin-bottom:4px; }
    .boleta-layout .boleta-meta dd { margin:0; font-size:13px; font-weight:500; color:#374151; overflow-wrap:anywhere; }
    .boleta-layout .boleta-average { padding:18px; border:1px solid #dbeafe; background:#eff6ff; border-radius:12px; text-align:center; display:flex; flex-direction:column; justify-content:center; gap:6px; }
    .boleta-layout .boleta-average.pendiente { border-color:#fde68a; background:#fffbeb; }
    .boleta-layout .boleta-average-label { margin:0; font-size:12px; font-weight:600; color:#4b5563; }
    .boleta-layout .boleta-average-value { margin:0; font-size:38px; font-weight:700; color:#1d4ed8; font-variant-numeric:tabular-nums; line-height:1.2; }
    .boleta-layout .boleta-average-value.es-pendiente { font-size:25px; color:#92400e; }
    .boleta-layout .boleta-average-caption { margin:0; font-size:11px; color:#4b5563; line-height:1.5; }
    .boleta-layout .boleta-table-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
    .boleta-layout .boleta-section-header { padding:18px 20px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; border-bottom:1px solid #e5e7eb; }
    .boleta-layout .boleta-section-title { margin:0; font-size:15px; font-weight:600; color:#111827; }
    .boleta-layout .boleta-section-help { margin:5px 0 0; font-size:12px; color:#6b7280; line-height:1.5; }
    .boleta-layout .boleta-status { display:inline-flex; align-items:center; gap:6px; padding:4px 9px; border-radius:6px; font-size:11px; font-weight:600; line-height:1.5; white-space:nowrap; }
    .boleta-layout .boleta-status.pendiente { background:#fef3c7; color:#92400e; }
    .boleta-layout .boleta-status.completa { background:#f0fdf4; color:#166534; }
    .boleta-layout .boleta-table { width:100%; table-layout:fixed; border-collapse:collapse; }
    .boleta-layout .boleta-table th { width:12%; padding:12px 10px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; background:#f9fafb; overflow-wrap:anywhere; }
    .boleta-layout .boleta-table th:first-child { width:40%; text-align:left; padding-left:20px; }
    .boleta-layout .boleta-table td { padding:18px 10px; text-align:center; vertical-align:middle; font-size:14px; color:#374151; font-variant-numeric:tabular-nums; border-top:1px solid #f1f5f9; }
    .boleta-layout .boleta-table .boleta-materia { padding-left:20px; text-align:left; }
    .boleta-layout .boleta-materia-title { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:start; gap:8px; margin-bottom:12px; }
    .boleta-layout .boleta-materia-name { margin:0; font-size:14px; font-weight:600; color:#111827; overflow-wrap:anywhere; line-height:1.5; }
    .boleta-layout .boleta-docentes { margin:0; display:grid; gap:6px; font-size:12px; line-height:1.5; }
    .boleta-layout .boleta-docentes div { display:flex; flex-wrap:wrap; gap:4px 6px; }
    .boleta-layout .boleta-docentes dt { color:#6b7280; font-weight:400; }
    .boleta-layout .boleta-docentes dd { margin:0; color:#4b5563; overflow-wrap:anywhere; }
    .boleta-layout .boleta-grade-pill { display:inline-flex; align-items:center; justify-content:center; min-width:42px; min-height:36px; padding:4px 8px; border-radius:8px; background:#f3f4f6; color:#111827; font-size:16px; font-weight:700; line-height:1.5; }
    .boleta-layout .boleta-grade-pill.sin-nota { color:#6b7280; font-size:12px; font-weight:500; }
    .boleta-layout .boleta-letter-pill { display:inline-flex; align-items:center; justify-content:center; padding:5px 10px; min-width:36px; border-radius:6px; font-size:12px; font-weight:700; color:#fff; }
    .boleta-layout .boleta-empty { margin:0; padding:36px 20px; text-align:center; font-size:13px; color:#6b7280; }
    .boleta-layout .boleta-selector { padding:24px; }
    .boleta-layout .boleta-selector-title { margin:0 0 12px; font-size:14px; font-weight:600; color:#111827; }
    .boleta-layout .boleta-selector-form { display:flex; flex-wrap:wrap; gap:10px; }
    .boleta-layout .boleta-selector-input { max-width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; font-size:14px; }
    .boleta-layout .boleta-selector-btn { background:#111827; color:#fff; }
    .boleta-layout .solo-lectores { position:absolute; width:1px; height:1px; margin:-1px; padding:0; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @container boleta-preview (max-width:760px) {
        .boleta-layout .boleta-info-card { grid-template-columns:minmax(0,1fr); gap:18px; }
        .boleta-layout .boleta-average { display:grid; grid-template-columns:minmax(0,1fr) auto; text-align:left; align-items:center; }
        .boleta-layout .boleta-average-caption { grid-column:1 / -1; }
        .boleta-layout .boleta-meta { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .boleta-layout .boleta-meta .meta-curso { grid-column:1 / -1; order:1; }
        .boleta-layout .boleta-table { display:block !important; table-layout:auto; overflow:visible !important; border-collapse:separate; }
        .boleta-layout .boleta-table thead { position:absolute; width:1px !important; min-width:0 !important; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
        .boleta-layout .boleta-table tbody { display:grid !important; width:100% !important; min-width:0 !important; gap:12px; padding:14px; box-sizing:border-box; background:#f8fafc; }
        .boleta-layout .boleta-table tr { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; padding:16px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; min-width:0; }
        .boleta-layout .boleta-table td { padding:10px 6px !important; white-space:normal !important; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; min-width:0; font-size:15px; overflow-wrap:anywhere; }
        .boleta-layout .boleta-table td::before { content:attr(data-label); display:block; margin-bottom:8px; font-size:10px; color:#6b7280; font-weight:600; }
        .boleta-layout .boleta-table .boleta-materia { grid-column:1 / -1; padding:0 0 12px !important; border:0; border-bottom:1px solid #f1f5f9; border-radius:0; background:#fff; }
        .boleta-layout .boleta-table .boleta-materia::before { display:none; }
        .boleta-layout .boleta-materia-title { display:flex; flex-wrap:wrap; justify-content:space-between; }
        .boleta-layout .boleta-table td:last-child { grid-column:span 2; }
        .boleta-layout .boleta-grade-pill { font-size:16px; }
    }
    @media (max-width:640px) {
        .boleta-layout { flex-direction:column; height:100dvh; }
        .boleta-layout > aside { width:100% !important; box-sizing:border-box; flex-direction:row !important; align-items:center; padding:8px 12px !important; overflow-x:auto !important; overflow-y:hidden !important; }
        .boleta-layout > aside > p, .boleta-layout > aside > div:first-child { display:none !important; }
        .boleta-layout > aside > a { flex-shrink:0; white-space:nowrap; }
        .boleta-layout > aside > div:last-child { margin:0 0 0 8px !important; padding:0 0 0 12px !important; border-top:0 !important; border-left:1px solid #e5e7eb; }
        .boleta-layout .boleta-main { min-height:0; }
        .boleta-layout .boleta-header { padding:14px 16px; gap:14px; }
        .boleta-layout .boleta-content { padding:16px; }
        .boleta-layout .boleta-card { padding:16px; }
        .boleta-layout .boleta-actions { width:100%; margin-left:0; }
        .boleta-layout .boleta-actions > *, .boleta-layout .boleta-actions form .boleta-btn { flex:1; }
        .boleta-layout .boleta-btn { padding:9px 12px; }
        .boleta-layout .boleta-student { gap:12px; }
        .boleta-layout .boleta-student-name { font-size:17px; }
        .boleta-layout .boleta-avatar { width:40px; height:40px; font-size:13px; }
    }
</style>
@endpush

<div class="boleta-layout">
    @include('components.admin-sidebar', ['active' => 'boletas'])
    <main class="boleta-main">
        <header class="boleta-header">
            <div class="boleta-heading">
                <x-boton-regreso :href="route('admin.boletas')">Volver a Boletas</x-boton-regreso>
                <div><h1 class="boleta-title">Boleta de notas</h1><p class="boleta-subtitle">Vista previa · Ciclo {{ $curso?->anio_lectivo ?? '—' }}</p></div>
            </div>
            @if($curso)
                <div class="boleta-actions">
                    @if(!isset($boletaHistorica) || !$boletaHistorica)
                        @if(isset($puede_emitir_academicamente) && $puede_emitir_academicamente)
                            <form method="POST" action="{{ route('admin.boletas.emitir') }}">
                                @csrf
                                <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
                                <input type="hidden" name="curso_id" value="{{ $curso->id }}">
                                <button class="boleta-btn boleta-emitir" type="submit">Emitir boleta</button>
                            </form>
                        @else
                            <button class="boleta-btn boleta-inactiva" type="button" disabled title="Calificaciones pendientes: no se puede emitir hasta completar todas las notas">Emitir boleta</button>
                        @endif
                    @else
                        <span class="boleta-btn boleta-emitida">✓ Boleta emitida</span>
                    @endif
                    <a class="boleta-btn boleta-pdf-link" href="{{ !empty($boletaHistorica) ? route('admin.boletas.emisiones.pdf', $boletaHistorica->id) : route('admin.boletas.pdf', [$alumno->id, 'curso_id' => $curso->id]) }}">{{ !empty($boletaHistorica) ? 'Descargar emisión' : 'Descargar PDF actual' }}</a>
                </div>
            @endif
        </header>

        <div class="boleta-content">
            @if(session('success'))
                <div role="status" style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:500;">{{ session('success') }}</div>
            @endif
            @if($errors->has('emision'))
                <div role="alert" style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:500;">{{ $errors->first('emision') }}</div>
            @endif

            @if($curso)
                @php $pg = $promedio_general ?? null; @endphp
                <section class="boleta-card boleta-info-card" aria-label="Resumen del alumno">
                    <div class="boleta-student">
                        <div class="boleta-avatar" aria-hidden="true">{{ strtoupper(substr($alumno->nombre,0,1).substr($alumno->apellido,0,1)) }}</div>
                        <div class="boleta-student-info">
                            <h2 class="boleta-student-name">{{ $alumno->nombre_completo }}</h2>
                            <dl class="boleta-meta">
                                <div><dt>Código</dt><dd>{{ $alumno->codigo }}</dd></div>
                                <div class="meta-curso"><dt>Curso</dt><dd>{{ $curso->nombre }}</dd></div>
                                <div><dt>Turno / nivel</dt><dd>{{ $curso->nivel }}</dd></div>
                            </dl>
                        </div>
                    </div>
                    <div class="boleta-average {{ $pg === null ? 'pendiente' : '' }}">
                        <h3 class="boleta-average-label">Promedio General</h3>
                        <p class="boleta-average-value {{ $pg === null ? 'es-pendiente' : '' }}">{{ $pg !== null ? round($pg, 1) : 'Pendiente' }}</p>
                        @if(isset($puede_emitir_academicamente) && !$puede_emitir_academicamente)
                            <p class="boleta-average-caption">Calificaciones pendientes</p>
                        @elseif(isset($puede_emitir_academicamente) && $puede_emitir_academicamente)
                            <p class="boleta-average-caption">Calificaciones completas · Concepto {{ $concepto_general ?? '—' }}</p>
                        @endif
                    </div>
                </section>

                <section class="boleta-table-card" aria-labelledby="titulo-notas">
                    <div class="boleta-section-header">
                        <div>
                            <h2 id="titulo-notas" class="boleta-section-title">Detalle de Notas</h2>
                            <p class="boleta-section-help">Maestro actual: docente asignado. Registrado por: autor de la calificación.</p>
                        </div>
                        <span class="boleta-status {{ !empty($puede_emitir_academicamente) ? 'completa' : 'pendiente' }}">{{ $materias_completas ?? 0 }} de {{ $materias_esperadas ?? 0 }} materias completas</span>
                    </div>
                    @if(count($filas ?? []) > 0)
                        <table class="boleta-table">
                            <caption class="solo-lectores">Calificaciones por materia en el orden académico del curso</caption>
                            <thead><tr>
                                <th scope="col">Materia y docentes</th><th scope="col">Laboratorio</th><th scope="col">Teórico</th>
                                <th scope="col">Práctico</th><th scope="col">Promedio</th><th scope="col">Concepto</th>
                            </tr></thead>
                            <tbody>
                                @foreach($filas as $fila)
                                    @php
                                        $nota = $fila['nota'];
                                        $p = $nota?->promedio;
                                        $letra = $fila['concepto'] ?? '—';
                                        $color = $letra === 'E' ? '#16a34a' : ($letra === 'MB' ? '#0284c7' : ($letra === 'B' ? '#d97706' : ($letra === 'R' ? '#f59e0b' : ($letra === 'D' ? '#dc2626' : '#9ca3af'))));
                                    @endphp
                                    <tr class="{{ $fila['completa'] ? 'materia-calificada' : 'materia-pendiente' }}">
                                        <td class="boleta-materia">
                                            <div class="boleta-materia-title">
                                                <h3 class="boleta-materia-name">{{ $fila['materia']->nombre ?? '—' }}</h3>
                                                <span class="boleta-status {{ $fila['completa'] ? 'completa' : 'pendiente' }}">{{ $fila['completa'] ? 'Calificada' : 'Pendiente' }}</span>
                                            </div>
                                            <dl class="boleta-docentes">
                                                <div><dt>Maestro actual:</dt><dd>@if($fila['detalle_curso']->maestro){{ $fila['detalle_curso']->maestro->nombre }} {{ $fila['detalle_curso']->maestro->apellido }}@else Sin asignar @endif</dd></div>
                                                <div><dt>Registrado por:</dt><dd>@if($nota?->maestro){{ $nota->maestro->nombre }} {{ $nota->maestro->apellido }}@elseif($nota) No disponible @else Sin registro @endif</dd></div>
                                            </dl>
                                        </td>
                                        <td data-label="Laboratorio">{{ $nota?->laboratorio ?? '—' }}</td>
                                        <td data-label="Teórico">{{ $nota?->examen_teorico ?? '—' }}</td>
                                        <td data-label="Práctico">{{ $nota?->practica ?? '—' }}</td>
                                        <td data-label="Promedio"><span class="boleta-grade-pill {{ $p === null ? 'sin-nota' : '' }}">{{ $p ?? '—' }}</span></td>
                                        <td data-label="Concepto"><span class="boleta-letter-pill" style="background:{{ $color }};">{{ $letra }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="boleta-empty">Sin materias esperadas para este curso.</p>
                    @endif
                </section>
            @elseif(isset($cursosAprobados) && isset($filtrosCursos))
                <section class="boleta-card boleta-selector">
                    <h2 class="boleta-selector-title">Seleccionar curso / ciclo</h2>
                    <form class="boleta-selector-form" method="GET" action="{{ route('admin.boletas.show', ['alumno_id' => $alumno->id]) }}">
                        <x-consulta-cursos-alcance :filtros="$filtrosCursos" />
                        @if($cursosAprobados->isNotEmpty())
                            <select name="curso_id" class="boleta-selector-input" aria-label="Curso / ciclo">
                                <option value="">-- Curso --</option>
                                @foreach($cursosAprobados as $c)
                                    <option value="{{ $c->id }}" data-estado-curso="{{ $c->estado_curso }}" @selected(request('curso_id') == $c->id)>{{ $c->nombre }} · {{ $c->nivel }} · {{ $c->seccion }} · {{ $c->anio_lectivo }} · {{ $c->estado_curso }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if($cursosAprobados->isEmpty())<p>No hay cursos activos disponibles. Incluye los cursos históricos para consultar otras boletas.</p>@endif
                        <button class="boleta-btn boleta-selector-btn" type="submit">Aplicar</button>
                    </form>
                </section>
            @else
                <p class="boleta-empty">El alumno no tiene inscripciones aprobadas.</p>
            @endif
        </div>
    </main>
</div>
</x-app-layout>
