<x-app-layout>
@push('styles')
<style>
    .mensualidades-page { display:flex; height:100vh; overflow:hidden; }
    .mensualidades-page .mp-main { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc; }
    .mensualidades-page .mp-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:18px 24px; background:#fff; border-bottom:1px solid #e5e7eb; }
    .mensualidades-page .mp-heading { display:flex; align-items:center; gap:12px; min-width:0; }
    .mensualidades-page .mp-logo { width:40px; height:40px; object-fit:cover; border:1px solid #e5e7eb; border-radius:12px; }
    .mensualidades-page h1 { margin:0; color:#111827; font-size:18px; font-weight:700; }
    .mensualidades-page .mp-muted { margin:4px 0 0; color:#6b7280; font-size:12px; line-height:1.6; }
    .mensualidades-page .mp-header-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; margin-left:auto; gap:10px; }
    .mensualidades-page .mp-content { flex:1; min-height:0; overflow-y:auto; padding:24px; }
    .mensualidades-page:has(.chk-mensualidad:checked) .mp-content { padding-bottom:120px; }
    .mensualidades-page .mp-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:12px; }
    .mensualidades-page .mp-stat { padding:20px; border:1px solid #e5e7eb; border-radius:14px; background:#fff; }
    .mensualidades-page .mp-stat h2 { margin:0; color:#4b5563; font-size:12px; font-weight:600; }
    .mensualidades-page .mp-stat-value { margin:10px 0 6px; color:#111827; font-size:28px; font-weight:700; font-variant-numeric:tabular-nums; }
    .mensualidades-page .mp-stat-value.pagado { color:#15803d; }
    .mensualidades-page .mp-stat-value.pendiente { color:#92400e; }
    .mensualidades-page .mp-stat-help { margin:0; color:#6b7280; font-size:11px; line-height:1.6; }
    .mensualidades-page .mp-panel { min-width:0; background:#fff; border:1px solid #e5e7eb; border-radius:14px; margin-bottom:18px; }
    .mensualidades-page .mp-filters { padding:20px; margin-top:20px; }
    .mensualidades-page h2 { margin:0; font-size:14px; font-weight:600; color:#111827; }
    .mensualidades-page .mp-filter-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-top:16px; }
    .mensualidades-page .mp-field { min-width:0; }
    .mensualidades-page .mp-field label, .mensualidades-page .mp-cycle-label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:7px; }
    .mensualidades-page .mp-control { width:100%; min-width:0; max-width:100%; height:42px; box-sizing:border-box; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; background:#fff; color:#111827; font:inherit; font-size:13px; }
    .mensualidades-page .mp-cycle { width:140px; }
    .mensualidades-page .mp-cycle-label { margin:0; }
    .mensualidades-page .mp-btn, .mp-batch .mp-btn { display:inline-flex; justify-content:center; align-items:center; gap:6px; padding:9px 14px; min-height:40px; box-sizing:border-box; border:1px solid #d1d5db; border-radius:8px; color:#374151; background:#fff; font-family:inherit; font-size:12px; font-weight:600; line-height:1.5; text-decoration:none; cursor:pointer; }
    .mensualidades-page .mp-btn-primary { background:#111827; border-color:#111827; color:#fff; }
    .mensualidades-page .mp-btn-primary:hover { background:#374151; }
    .mensualidades-page :is(a,button,input,select):focus-visible { outline:2px solid #2563eb; outline-offset:3px; }
    .mensualidades-page .mp-filter-footer { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding-top:16px; margin-top:16px; border-top:1px solid #f1f5f9; }
    .mensualidades-page .mp-button-group { display:flex; gap:8px; }
    .mensualidades-page .mp-list-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin:22px 0 14px; }
    .mensualidades-page .mp-badge { display:inline-flex; align-items:center; padding:4px 9px; border-radius:6px; background:#f3f4f6; color:#4b5563; font-size:11px; font-weight:600; line-height:1.6; }
    .mensualidades-page .mp-badge.pagado { background:#f0fdf4; color:#166534; }
    .mensualidades-page .mp-badge.pendiente { background:#fffbeb; color:#92400e; }
    .mensualidades-page .grupo-curso { container:mp-course / inline-size; }
    .mensualidades-page .mp-course-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px 16px; padding:12px 20px; border-bottom:1px solid #e5e7eb; background:#f9fafb; border-radius:14px 14px 0 0; }
    .mensualidades-page .mp-course-info { flex:1; min-width:0; overflow-wrap:anywhere; }
    .mensualidades-page .mp-master { display:inline-flex; align-items:center; flex-shrink:0; gap:8px; margin-left:auto; padding:7px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; font-size:11px; color:#4b5563; cursor:pointer; }
    .mensualidades-page .mp-master input { width:15px; height:15px; margin:0; flex-shrink:0; }
    .mensualidades-page .mp-student + .mp-student { border-top:1px solid #f1f5f9; }
    .mensualidades-page .mp-student-row { display:flex; align-items:center; justify-content:space-between; min-height:76px; box-sizing:border-box; gap:20px; padding:12px 20px; }
    .mensualidades-page .mp-student-identity { display:flex; align-items:center; flex:1; min-width:0; gap:12px; }
    .mensualidades-page .mp-student-avatar { display:flex; align-items:center; justify-content:center; width:38px; height:38px; flex-shrink:0; border-radius:50%; background:#f3f4f6; color:#374151; font-size:12px; font-weight:700; }
    .mensualidades-page .mp-student-info { min-width:0; }
    .mensualidades-page .mp-student-name { margin:0; color:#111827; font-size:14px; font-weight:600; line-height:1.45; overflow-wrap:anywhere; }
    .mensualidades-page .mp-student-code { margin:2px 0 0; color:#6b7280; font-size:11px; line-height:1.5; overflow-wrap:anywhere; }
    .mensualidades-page .mp-student-right { display:flex; align-items:center; justify-content:flex-end; gap:16px; flex-shrink:0; }
    .mensualidades-page .mp-summary { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin:0; }
    .mensualidades-page .mp-summary-badges { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
    .mensualidades-page .mp-summary-mobile, .mensualidades-page .mp-mobile-balance { display:none; }
    .mensualidades-page .mp-money { width:108px; text-align:right; font-variant-numeric:tabular-nums; }
    .mensualidades-page .mp-money strong { font-size:15px; color:#374151; }
    .mensualidades-page .mp-money .mp-muted { margin-top:1px; font-size:11px; }
    .mensualidades-page .mp-student-right > .mp-btn { width:160px; min-height:38px; padding:8px 10px; flex-shrink:0; }
    .mensualidades-page .mp-details { padding:0 20px 18px; }
    .mensualidades-page .mp-detail-panel { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff; }
    .mensualidades-page .mp-detail-note { padding:14px 16px; margin:0; color:#6b7280; font-size:12px; line-height:1.6; background:#f9fafb; }
    .mensualidades-page .mp-month-list { margin:0; padding:0; list-style:none; }
    .mensualidades-page .mp-month { display:grid; grid-template-columns:20px minmax(0,1fr) auto auto; align-items:center; gap:16px; padding:14px 16px; border-top:1px solid #f1f5f9; }
    .mensualidades-page .mp-month-name { margin:0; color:#111827; font-size:13px; font-weight:600; }
    .mensualidades-page .mp-month-amount { display:flex; flex-direction:column; align-items:flex-end; gap:5px; font-size:14px; font-weight:600; color:#374151; font-variant-numeric:tabular-nums; }
    .mensualidades-page .mp-month form { margin:0; }
    .mensualidades-page .mp-btn-cobrar { color:#166534; background:#f0fdf4; border-color:#bbf7d0; }
    .mensualidades-page .mp-btn-revertir { color:#b91c1c; background:#fef2f2; border-color:#fecaca; }
    .mensualidades-page .mp-pagination { padding:14px 16px; border-top:1px solid #e5e7eb; }
    .mensualidades-page .mp-empty { padding:36px 20px; text-align:center; color:#6b7280; font-size:13px; line-height:1.7; }
    .mp-batch { position:fixed; bottom:16px; left:50%; transform:translateX(-50%); z-index:150; max-width:calc(100% - 32px); width:max-content; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:12px; padding:12px 16px; box-sizing:border-box; border-radius:12px; background:#111827; box-shadow:0 8px 24px #0003; }
    .mp-batch p { margin:0; font-size:13px; color:#fff; }
    .mp-batch .mp-btn-cobrar { color:#fff; background:#15803d; border-color:#15803d; }
    @container mp-course (max-width:1000px) {
        .mensualidades-page .mp-student-row { display:grid; grid-template-columns:minmax(0,1fr) 160px; gap:8px 16px; }
        .mensualidades-page .mp-student-identity { grid-column:1; grid-row:1; }
        .mensualidades-page .mp-student-right { display:contents; }
        .mensualidades-page .mp-summary { grid-column:1; grid-row:2; }
        .mensualidades-page .mp-money { grid-column:2; grid-row:2; justify-self:end; }
        .mensualidades-page .mp-student-right > .mp-btn, .mensualidades-page .mp-no-months { grid-column:2; grid-row:1; justify-self:end; }
    }
    @container mp-course (max-width:520px) {
        .mensualidades-page .mp-student-row { grid-template-columns:minmax(0,1fr) 140px; gap:10px 12px; padding:12px 16px; }
        .mensualidades-page .mp-student-identity { gap:10px; }
        .mensualidades-page .con-mensualidades .mp-student-identity { grid-column:1 / -1; }
        .mensualidades-page .mp-student-info { flex:1; }
        .mensualidades-page .mp-student-avatar { width:34px; height:34px; font-size:11px; }
        .mensualidades-page .mp-student-right > .mp-btn { grid-row:2; width:140px; font-size:11px; }
        .mensualidades-page .mp-summary-badges, .mensualidades-page .mp-money { display:none; }
        .mensualidades-page .mp-summary-mobile { display:block; margin:0; font-size:11px; color:#6b7280; line-height:1.6; }
        .mensualidades-page .mp-student-code { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:2px 10px; }
        .mensualidades-page .mp-mobile-balance { display:inline; font-size:10px; white-space:nowrap; }
        .mensualidades-page .mp-mobile-balance strong { color:#374151; font-size:12px; }
        .mensualidades-page .mp-student-row.sin-mensualidades { display:flex; }
        .mensualidades-page .sin-mensualidades .mp-student-right { display:flex; }
        .mensualidades-page .mp-course-heading { display:grid; grid-template-columns:minmax(0,1fr); gap:8px; padding:12px 16px; }
        .mensualidades-page .mp-master { justify-self:start; margin-left:0; padding:5px 8px; }
    }
    @media (max-width:1000px) {
        .mensualidades-page .mp-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:640px) {
        .mensualidades-page { flex-direction:column; height:100dvh; }
        .mensualidades-page > aside { width:100% !important; box-sizing:border-box; flex-direction:row !important; align-items:center; padding:8px 12px !important; overflow-x:auto !important; overflow-y:hidden !important; }
        .mensualidades-page > aside > p, .mensualidades-page > aside > div:first-child { display:none !important; }
        .mensualidades-page > aside > a { flex-shrink:0; white-space:nowrap; }
        .mensualidades-page > aside > div:last-child { margin:0 0 0 8px !important; padding:0 0 0 12px !important; border-top:0 !important; border-left:1px solid #e5e7eb; }
        .mensualidades-page .mp-main { min-height:0; }
        .mensualidades-page .mp-header, .mensualidades-page .mp-content { padding:16px; }
        .mensualidades-page .mp-header-actions { width:100%; }
        .mensualidades-page .mp-header-actions > .mp-btn { flex:1; }
        .mensualidades-page .mp-stats { grid-template-columns:minmax(0,1fr); gap:10px; }
        .mensualidades-page .mp-stat { padding:16px; }
        .mensualidades-page .mp-stat-value { font-size:24px; }
        .mensualidades-page .mp-filter-grid { grid-template-columns:minmax(0,1fr); }
        .mensualidades-page .mp-filters { padding:16px; }
        .mensualidades-page .mp-details { padding:0 12px 12px; }
        .mensualidades-page .mp-month { grid-template-columns:20px minmax(0,1fr) auto; gap:10px; padding:12px; }
        .mensualidades-page .mp-month form { grid-column:2 / -1; }
        .mensualidades-page .mp-month form .mp-btn { width:100%; }
    }
</style>
@endpush
<div class="mensualidades-page" data-mensualidades-panel>
    @include('components.admin-sidebar', ['active' => 'mensualidades'])
    <main class="mp-main">
        <header class="mp-header">
            <div class="mp-heading">
                <img class="mp-logo" src="{{ asset('images/logo_itm.jpg') }}" alt="ITM">
                <div><h1>Mensualidades</h1><p class="mp-muted">Gestión de cuotas · Cuota mensual $25.00</p></div>
            </div>
            <div class="mp-header-actions">
                <label class="mp-cycle-label" for="cicloMensualidades">Ciclo</label>
                <select id="cicloMensualidades" class="mp-control mp-cycle" name="anio" form="mensualidadesFiltros" onchange="this.form.requestSubmit()">
                    @foreach($aniosDisponibles as $a)<option value="{{ $a }}" @selected($a == $anioSeleccionado)>{{ $a }} {{ $a == date('Y') ? '(actual)' : '' }}</option>@endforeach
                </select>
                <button type="button" class="mp-btn mp-btn-primary" onclick="abrirModalGenerar()">+ Generar mensualidades</button>
            </div>
        </header>
        <div class="mp-content">
            @if(session('success'))<div class="mp-panel mp-empty" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="mp-panel mp-empty" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="mp-panel mp-empty" role="alert">{{ $errors->first() }}</div>@endif
            @if($anioSeleccionado != date('Y'))
                <p class="mp-muted" style="margin:0 0 16px;">Estás viendo el ciclo {{ $anioSeleccionado }}. <a href="{{ route('admin.mensualidades') }}">Ver ciclo actual</a></p>
            @endif
            <section class="mp-stats" aria-label="Resumen financiero">
                <article class="mp-stat"><h2>Alumnos inscritos</h2><p class="mp-stat-value">{{ number_format($totalAlumnos) }}</p><p class="mp-stat-help">Alumnos con inscripción aprobada · Todos los ciclos</p></article>
                <article class="mp-stat"><h2>Total cobrado</h2><p class="mp-stat-value pagado">${{ number_format($totalPagado, 2) }}</p><p class="mp-stat-help">Mensualidades pagadas del ciclo {{ $anioSeleccionado }}</p></article>
                <article class="mp-stat"><h2>Pendiente de cobro</h2><p class="mp-stat-value pendiente">${{ number_format($totalPendiente, 2) }}</p><p class="mp-stat-help">Mensualidades pendientes del ciclo {{ $anioSeleccionado }}</p></article>
            </section>
            <p class="mp-muted">Los importes corresponden al ciclo completo y se mantienen al filtrar el listado.</p>
            <section class="mp-panel mp-filters" aria-labelledby="tituloFiltrosMensualidades">
                <h2 id="tituloFiltrosMensualidades">Consultar mensualidades</h2>
                <form id="mensualidadesFiltros" method="GET" action="{{ route('admin.mensualidades') }}">
                    <div class="mp-filter-grid">
                        <div class="mp-field"><label for="buscarAlumno">Alumno</label><input class="mp-control" id="buscarAlumno" name="q" type="search" maxlength="100" value="{{ $filtros['q'] }}" placeholder="Nombre o código" oninput="filtrarMensualidades()"></div>
                        <div class="mp-field"><label for="filtrarCurso">Curso</label><select class="mp-control" id="filtrarCurso" name="curso_id" onchange="this.form.requestSubmit()"><option value="">Todos los cursos</option>@foreach($cursos as $curso)<option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }} — {{ $curso->nivel }}</option>@endforeach</select></div>
                        <div class="mp-field"><label for="filtrarMes">Mes</label><select class="mp-control" id="filtrarMes" name="mes" onchange="this.form.requestSubmit()"><option value="">Todos los meses</option>@foreach($meses as $nombre)<option value="{{ $nombre }}" @selected(($filtros['mes'] ?? '') === $nombre)>{{ $nombre }}</option>@endforeach</select></div>
                        <div class="mp-field"><label for="filtrarEstado">Estado</label><select class="mp-control" id="filtrarEstado" name="estado" onchange="this.form.requestSubmit()"><option value="">Todos los estados</option>@foreach(['Pendiente','Pagado'] as $estado)<option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>@endforeach</select></div>
                    </div>
                    <div class="mp-filter-footer"><p class="mp-muted">Período: {{ $filtros['mes'] ?? 'Todos los meses' }} · {{ $anioSeleccionado }}</p><div class="mp-button-group"><a class="mp-btn" href="{{ route('admin.mensualidades', ['anio' => $anioSeleccionado]) }}">Limpiar</a><button class="mp-btn mp-btn-primary" type="submit">Filtrar</button></div></div>
                </form>
            </section>
            <div class="mp-list-heading"><div><h2>Alumnos por curso</h2><p class="mp-muted">Mensualidades del alumno en el ciclo. Expande para consultar los meses y gestionar sus pagos.</p></div><span class="mp-badge">{{ number_format($listado->total()) }} resultados · {{ $listado->perPage() }} por página</span></div>
            @forelse($grupos as $cursoId => $inscripciones)
                @php $curso = $inscripciones->first()->curso; @endphp
                <section class="mp-panel grupo-curso" data-curso="{{ $cursoId }}" aria-labelledby="curso-mensualidades-{{ $cursoId }}">
                    <header class="mp-course-heading"><div class="mp-course-info"><h2 id="curso-mensualidades-{{ $cursoId }}">{{ $curso->nombre }}</h2><p class="mp-muted">{{ $curso->nivel }} · Sección {{ $curso->seccion }} · {{ $inscripciones->count() }} en esta página</p></div><label class="mp-master"><input class="chk-curso" type="checkbox" data-curso="{{ $cursoId }}" title="Seleccionar pendientes visibles de este curso"> Seleccionar pendientes visibles</label></header>
                    @foreach($inscripciones as $ins)
                        <article class="mp-student" data-alumno="{{ $ins->alumno_id }}" data-curso="{{ $cursoId }}">
                            <div class="mp-student-row {{ $ins->registros ? 'con-mensualidades' : 'sin-mensualidades' }}">
                                <div class="mp-student-identity">
                                    <span class="mp-student-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($ins->alumno->nombre, 0, 1).mb_substr($ins->alumno->apellido, 0, 1)) }}</span>
                                    <div class="mp-student-info"><h3 class="mp-student-name">{{ $ins->alumno->nombre_completo }}</h3><p class="mp-student-code"><span>{{ $ins->alumno->codigo }}</span>@if($ins->registros)<span class="mp-mobile-balance" title="Pendiente en los resultados"><strong>${{ number_format($ins->saldo, 2) }}</strong> pendiente</span>@endif</p>
                                    <a class="mp-btn" href="{{ route('admin.pagos.estado-cuenta', ['alumno' => $ins->alumno_id, 'anio' => $anioSeleccionado]) }}">Ver estado de cuenta en Pagos</a></div>
                                </div>
                                <div class="mp-student-right">
                                    @if($ins->registros)
                                        <div class="mp-summary">
                                        <div class="mp-summary-badges">
                                        <span class="mp-badge">{{ $ins->registros }} {{ $ins->registros == 1 ? 'mensualidad' : 'mensualidades' }}</span><span class="mp-badge pagado">Pagadas: {{ $ins->pagadas }}</span><span class="mp-badge pendiente">Pendientes: {{ $ins->pendientes }}</span>
                                        </div>
                                        <p class="mp-summary-mobile">{{ $ins->registros }} {{ $ins->registros == 1 ? 'cuota' : 'cuotas' }} · {{ $ins->pagadas }} {{ $ins->pagadas == 1 ? 'pagada' : 'pagadas' }} · {{ $ins->pendientes }} {{ $ins->pendientes == 1 ? 'pendiente' : 'pendientes' }}</p>
                                        </div>
                                    @else<span class="mp-badge mp-no-months">Sin generar</span>@endif
                                @if($ins->registros)
                                    <div class="mp-money"><strong>${{ number_format($ins->saldo, 2) }}</strong><p class="mp-muted" title="Pendiente en los resultados">Pendiente</p></div>
                                    <button class="mp-btn" type="button" data-ver-mensualidades aria-expanded="false" aria-controls="mensualidades-{{ $cursoId }}-{{ $ins->alumno_id }}" data-url="{{ route('admin.mensualidades.detalles', ['curso' => $cursoId, 'alumno' => $ins->alumno_id] + $filtros) }}">Ver mensualidades</button>
                                @endif
                                </div>
                            </div>
                            <div class="mp-details" id="mensualidades-{{ $cursoId }}-{{ $ins->alumno_id }}" hidden><div class="mp-detail-panel" data-mensualidades-detalle aria-live="polite"></div></div>
                        </article>
                    @endforeach
                </section>
            @empty<div class="mp-panel mp-empty">No hay alumnos para los filtros seleccionados. Prueba otro ciclo o limpia los filtros.</div>@endforelse
            @if($listado->hasPages())<div class="mp-panel mp-pagination">{{ $listado->links() }}</div>@endif
        </div>
    </main>
</div>

<form id="formLote" class="mp-batch" method="POST" action="{{ route('admin.mensualidades.pagarLote') }}" onsubmit="return prepararCobroLote(event)">
    @csrf
    <p><strong id="loteCantidad">0</strong> seleccionadas · <strong id="loteTotal">$0.00</strong></p>
    <button class="mp-btn mp-btn-cobrar" type="submit">✓ Cobrar seleccionadas</button>
    <button class="mp-btn" type="button" onclick="limpiarSeleccionLote()">Cancelar</button>
</form>
@include('admin.partials.modal-generar-mensualidades')
@push('scripts')
    @vite('resources/js/admin/mensualidades.js')
@endpush
</x-app-layout>
