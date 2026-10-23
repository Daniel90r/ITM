<x-app-layout>
@push('styles')
<style>
    .asistencias-page { display:flex; height:100vh; overflow:hidden; }
    .asistencias-page .reporte-main { flex:1; min-width:0; display:flex; flex-direction:column; background:#f8fafc; overflow:hidden; }
    .asistencias-page .reporte-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:18px 24px; background:#fff; border-bottom:1px solid #e5e7eb; }
    .asistencias-page .reporte-identidad { display:flex; align-items:center; gap:12px; min-width:0; }
    .asistencias-page .reporte-logo { width:40px; height:40px; flex-shrink:0; border:1px solid #e5e7eb; border-radius:12px; object-fit:cover; }
    .asistencias-page h1 { margin:0; font-size:18px; font-weight:700; color:#111827; line-height:1.4; }
    .asistencias-page .reporte-descripcion { margin:4px 0 0; font-size:12px; color:#6b7280; line-height:1.6; }
    .asistencias-page .reporte-periodo { margin:0; padding:8px 12px; background:#f3f4f6; border-radius:8px; font-size:12px; color:#4b5563; line-height:1.6; }
    .asistencias-page .reporte-contenido { flex:1; min-height:0; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:20px; }
    .asistencias-page .reporte-contenido > * { flex-shrink:0; }
    .asistencias-page .resumen-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; }
    .asistencias-page .resumen-tarjeta { padding:18px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; }
    .asistencias-page .resumen-etiqueta { margin:0; font-size:12px; font-weight:600; color:#4b5563; }
    .asistencias-page .resumen-valor { margin:10px 0 4px; font-size:28px; font-weight:700; color:#111827; line-height:1.2; font-variant-numeric:tabular-nums; }
    .asistencias-page .resumen-ayuda { margin:0; font-size:11px; color:#6b7280; line-height:1.5; }
    .asistencias-page .valor-presente { color:#15803d; }
    .asistencias-page .valor-ausente { color:#b91c1c; }
    .asistencias-page .valor-porcentaje { color:#2563eb; }
    .asistencias-page .reporte-panel { background:#fff; border:1px solid #e5e7eb; border-radius:14px; min-width:0; }
    .asistencias-page .filtros-panel { padding:20px; }
    .asistencias-page h2 { margin:0; font-size:14px; font-weight:600; color:#111827; }
    .asistencias-page .filtros-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-top:18px; }
    .asistencias-page .filtro-campo { min-width:0; }
    .asistencias-page .filtro-campo label { display:block; margin-bottom:7px; color:#374151; font-size:12px; font-weight:600; }
    .asistencias-page .filtro-control { width:100%; min-width:0; box-sizing:border-box; height:42px; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; background:#fff; color:#111827; font-family:inherit; font-size:13px; }
    .asistencias-page .filtros-acciones { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; border-top:1px solid #f1f5f9; margin-top:18px; padding-top:16px; }
    .asistencias-page .filtros-botones { display:flex; gap:8px; }
    .asistencias-page .reporte-boton { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:8px 16px; border:1px solid #d1d5db; border-radius:8px; font-family:inherit; font-size:12px; font-weight:600; color:#374151; background:#fff; text-decoration:none; cursor:pointer; box-sizing:border-box; }
    .asistencias-page .boton-filtrar { background:#2563eb; border-color:#2563eb; color:#fff; }
    .asistencias-page .boton-filtrar:hover { background:#1d4ed8; }
    .asistencias-page .reporte-boton:not(.boton-filtrar):hover { background:#f9fafb; }
    .asistencias-page :is(a,button,input,select,[tabindex]):focus-visible { outline:2px solid #2563eb; outline-offset:3px; }
    .asistencias-page .listado-header { padding:18px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; border-bottom:1px solid #e5e7eb; }
    .asistencias-page .listado-cantidad { padding:5px 10px; background:#eff6ff; border-radius:6px; font-size:12px; font-weight:600; color:#1d4ed8; }
    .asistencias-page .tabla-scroll { overflow-x:auto; border-radius:0 0 14px 14px; }
    .asistencias-page .reporte-tabla { width:100%; min-width:800px; border-collapse:collapse; text-align:left; }
    .asistencias-page .reporte-tabla th { padding:12px 16px; background:#f9fafb; font-size:11px; font-weight:600; color:#6b7280; letter-spacing:.04em; text-transform:uppercase; }
    .asistencias-page .reporte-tabla td { padding:16px; border-top:1px solid #f1f5f9; vertical-align:middle; color:#4b5563; font-size:13px; line-height:1.5; }
    .asistencias-page .reporte-tabla tbody tr:hover { background:#fafcff; }
    .asistencias-page .alumno-nombre { margin:0; font-size:14px; font-weight:600; color:#111827; }
    .asistencias-page .dato-principal { margin:0; color:#374151; font-weight:500; }
    .asistencias-page .dato-secundario { margin:4px 0 0; font-size:11px; color:#6b7280; }
    .asistencias-page .materia-nombre { margin:0; color:#1d4ed8; font-weight:500; }
    .asistencias-page .fecha-registro { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .asistencias-page .estado-columna { text-align:center; }
    .asistencias-page .estado-etiqueta { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; }
    .asistencias-page .estado-etiqueta::before { content:''; width:6px; height:6px; background:currentColor; border-radius:50%; }
    .asistencias-page .estado-presente { background:#f0fdf4; color:#15803d; }
    .asistencias-page .estado-ausente { background:#fef2f2; color:#b91c1c; }
    .asistencias-page .estado-permiso { background:#fffbeb; color:#92400e; }
    .asistencias-page .estado-otro { background:#f3f4f6; color:#4b5563; }
    .asistencias-page .resumen-alumno { display:flex; flex-wrap:wrap; gap:6px; }
    .asistencias-page .curso-alumno + .curso-alumno { margin-top:10px; }
    .asistencias-page .detalle-fila > td { padding:0 16px 16px; background:#f8fafc; }
    .asistencias-page .detalle-panel { border:1px solid #e5e7eb; border-radius:10px; background:#fff; overflow:hidden; }
    .asistencias-page .detalle-panel .reporte-tabla { min-width:700px; }
    .asistencias-page .detalle-panel .reporte-tabla td { padding:10px 14px; font-size:12px; }
    .asistencias-page .detalle-mensaje { padding:18px; margin:0; color:#6b7280; font-size:13px; }
    .asistencias-page .detalle-paginacion { padding:12px 16px; border-top:1px solid #e5e7eb; }
    .asistencias-page .conteo-registros { font-variant-numeric:tabular-nums; font-weight:600; }
    .asistencias-page .listado-footer { border-top:1px solid #e5e7eb; padding:16px 20px; }
    .asistencias-page .estado-vacio { padding:48px 20px; text-align:center; }
    .asistencias-page .vacio-icono { display:block; width:44px; height:44px; margin:0 auto 14px; color:#9ca3af; }
    .asistencias-page .estado-vacio h3 { margin:0 0 8px; color:#374151; font-size:14px; font-weight:600; }
    .asistencias-page .estado-vacio p { margin:0 auto 18px; max-width:360px; color:#6b7280; font-size:13px; line-height:1.6; }
    .asistencias-page .solo-lectores { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @media (max-width:1100px) { .asistencias-page .resumen-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:640px) {
        .asistencias-page { flex-direction:column; height:100dvh; }
        .asistencias-page > aside { width:100% !important; box-sizing:border-box; flex-direction:row !important; align-items:center; padding:8px 12px !important; overflow-x:auto !important; overflow-y:hidden !important; }
        .asistencias-page > aside > p, .asistencias-page > aside > div:first-child { display:none !important; }
        .asistencias-page > aside > a { flex-shrink:0; white-space:nowrap; }
        .asistencias-page > aside > div:last-child { margin:0 0 0 8px !important; padding:0 0 0 12px !important; border-top:0 !important; border-left:1px solid #e5e7eb; }
        .asistencias-page .reporte-main { min-height:0; }
        .asistencias-page .reporte-header { padding:16px; }
        .asistencias-page .reporte-contenido { padding:16px; gap:16px; }
        .asistencias-page .reporte-periodo { width:100%; box-sizing:border-box; }
        .asistencias-page .resumen-grid { gap:10px; }
        .asistencias-page .resumen-tarjeta { padding:14px; }
        .asistencias-page .resumen-valor { font-size:24px; }
        .asistencias-page .filtros-panel { padding:16px; }
        .asistencias-page .filtros-grid { grid-template-columns:minmax(0,1fr); }
        .asistencias-page .filtros-botones { width:100%; }
        .asistencias-page .reporte-boton { flex:1; }
        .asistencias-page .grupos-tabla { display:block; min-width:0; }
        /* Neutralizar solo aquí el ancho mínimo global de las tablas móviles. */
        .asistencias-page .grupos-tabla > thead { position:absolute; width:1px !important; min-width:0 !important; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
        .asistencias-page .grupos-tabla > tbody { display:block !important; min-width:0 !important; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id] { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; padding:16px; border-top:1px solid #e5e7eb; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id]:first-child { border-top:0; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id] > td { padding:0 !important; border:0; white-space:normal !important; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id] > td:nth-child(-n+3) { grid-column:1 / -1; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id] > td:nth-child(4) { align-self:center; }
        .asistencias-page .grupos-tabla > tbody > tr[data-alumno-id] > td:nth-child(4)::before { content:'Registros: '; font-weight:400; }
        .asistencias-page .grupos-tabla > tbody > .detalle-fila:not([hidden]), .asistencias-page .grupos-tabla > tbody > .detalle-fila > td { display:block; width:100%; min-width:0; box-sizing:border-box; }
        .asistencias-page .grupos-tabla > tbody > .detalle-fila > td { padding:0 12px 12px !important; white-space:normal !important; }
        .asistencias-page .detalle-panel { width:100%; min-width:0; box-sizing:border-box; }
        .asistencias-page .detalle-fila[hidden] { display:none !important; }
    }
</style>
@endpush

<div class="asistencias-page" data-reporte-asistencias>
    @include('components.admin-sidebar', ['active' => 'asistencias'])
    <main class="reporte-main">
        <header class="reporte-header">
            <div class="reporte-identidad">
                <img class="reporte-logo" src="{{ asset('images/logo_itm.jpg') }}" alt="ITM">
                <div>
                    <h1>Reportes de asistencia</h1>
                    @if($cursoId && $detalleCursoId)
                        <a class="reporte-boton" href="{{ route('admin.asistencias.pdf', $filtros) }}">Descargar PDF</a>
                    @else
                        <p class="reporte-descripcion">Selecciona curso y materia para descargar el Control de Asistencia PDF.</p>
                    @endif
                    <p class="reporte-descripcion">Consulta la asistencia de los alumnos por curso, materia, docente y período.</p>
                </div>
            </div>
            <p class="reporte-periodo" aria-label="Período consultado">
                @if($fechaDesde || $fechaHasta)
                    {{ $fechaDesde ? 'Desde '.$fechaDesde : 'Sin fecha inicial' }} · {{ $fechaHasta ? 'Hasta '.$fechaHasta : 'Sin fecha final' }}
                @else
                    Período: todas las fechas
                @endif
            </p>
        </header>

        <div class="reporte-contenido">
            <section class="resumen-grid" aria-label="Resumen de los resultados filtrados">
                <article class="resumen-tarjeta">
                    <h2 class="resumen-etiqueta">Total de registros</h2>
                    <p class="resumen-valor">{{ number_format($resumen['total']) }}</p>
                    <p class="resumen-ayuda">Según los filtros aplicados</p>
                </article>
                <article class="resumen-tarjeta">
                    <h2 class="resumen-etiqueta">Presentes</h2>
                    <p class="resumen-valor valor-presente">{{ number_format($resumen['presentes']) }}</p>
                    <p class="resumen-ayuda">Registros de presencia</p>
                </article>
                <article class="resumen-tarjeta">
                    <h2 class="resumen-etiqueta">Ausentes</h2>
                    <p class="resumen-valor valor-ausente">{{ number_format($resumen['ausentes']) }}</p>
                    <p class="resumen-ayuda">Registros de ausencia</p>
                </article>
                <article class="resumen-tarjeta">
                    <h2 class="resumen-etiqueta">Porcentaje de asistencia</h2>
                    <p class="resumen-valor valor-porcentaje">{{ $resumen['porcentaje'] === null ? '—' : number_format($resumen['porcentaje'], 1).'%' }}</p>
                    <p class="resumen-ayuda">{{ $resumen['total'] ? 'Presentes / total, incluidos permisos' : 'Sin registros para calcular' }}</p>
                </article>
                @if($resumen['permisos'])
                    <article class="resumen-tarjeta">
                        <h2 class="resumen-etiqueta">Permisos</h2>
                        <p class="resumen-valor">{{ number_format($resumen['permisos']) }}</p>
                        <p class="resumen-ayuda">Incluidos en el total de registros</p>
                    </article>
                @endif
            </section>

            <section class="reporte-panel filtros-panel" aria-labelledby="titulo-filtros">
                <h2 id="titulo-filtros">Filtrar asistencias</h2>
                <form method="GET" action="{{ route('admin.asistencias') }}">
                    <div class="filtros-grid">
                        <div class="filtro-campo">
                            <label for="asistencia-busqueda">Alumno</label>
                            <input id="asistencia-busqueda" class="filtro-control" type="search" name="q" maxlength="100" value="{{ $busqueda }}" placeholder="Nombre o código">
                        </div>
                        <div class="filtro-campo">
                            <label for="asistencia-curso">Curso</label>
                            <select id="asistencia-curso" class="filtro-control" name="curso_id" onchange="this.form.submit()" aria-describedby="ayuda-filtros">
                                <option value="">Todos los cursos</option>
                                @foreach($cursos as $curso)
                                    <option value="{{ $curso->id }}" @selected($cursoId == $curso->id)>{{ $curso->nombre }} — {{ $curso->nivel }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($detalleCursos->count())
                            <div class="filtro-campo">
                                <label for="asistencia-materia">Materia / Programa</label>
                                <select id="asistencia-materia" class="filtro-control" name="detalle_curso_id">
                                    <option value="">Todas las materias</option>
                                    @foreach($detalleCursos as $dc)
                                        <option value="{{ $dc->id }}" @selected($detalleCursoId == $dc->id)>{{ $dc->materia->nombre }} ({{ $dc->materia->codigo }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="filtro-campo">
                            <label for="asistencia-docente">Docente</label>
                            <select id="asistencia-docente" class="filtro-control" name="maestro_id">
                                <option value="">Todos los docentes</option>
                                @foreach($maestros as $maestro)
                                    <option value="{{ $maestro->id }}" @selected($maestroId == $maestro->id)>{{ $maestro->nombre_completo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filtro-campo">
                            <label for="asistencia-desde">Fecha desde</label>
                            <input id="asistencia-desde" class="filtro-control" type="date" name="fecha_desde" value="{{ $fechaDesde }}">
                        </div>
                        <div class="filtro-campo">
                            <label for="asistencia-hasta">Fecha hasta</label>
                            <input id="asistencia-hasta" class="filtro-control" type="date" name="fecha_hasta" value="{{ $fechaHasta }}">
                        </div>
                    </div>
                    <div class="filtros-acciones">
                        <p id="ayuda-filtros" class="resumen-ayuda">Al cambiar de curso se actualizan las materias disponibles.</p>
                        <div class="filtros-botones">
                            <a class="reporte-boton" href="{{ route('admin.asistencias') }}">Limpiar</a>
                            <button class="reporte-boton boton-filtrar" type="submit">Filtrar</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="reporte-panel" aria-labelledby="titulo-listado">
                <div class="listado-header">
                    <div>
                        <h2 id="titulo-listado">Asistencias por alumno</h2>
                        <p class="dato-secundario">Expande un alumno para consultar su historial filtrado. Se muestran hasta {{ $alumnos->perPage() }} alumnos por página.</p>
                    </div>
                    <span class="listado-cantidad">{{ number_format($alumnos->total()) }} {{ $alumnos->total() === 1 ? 'alumno' : 'alumnos' }} · {{ number_format($resumen['total']) }} {{ $resumen['total'] === 1 ? 'registro' : 'registros' }}</span>
                </div>
                @if($alumnos->isEmpty())
                    <div class="estado-vacio">
                        <svg class="vacio-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18M9 16h6"/></svg>
                        <h3>No hay asistencias para mostrar</h3>
                        <p>No se encontraron registros con los filtros seleccionados. Prueba otro período o limpia los filtros.</p>
                        <a class="reporte-boton" href="{{ route('admin.asistencias') }}">Limpiar filtros</a>
                    </div>
                @else
                    <div class="tabla-scroll" role="region" aria-labelledby="titulo-listado" tabindex="0">
                        <table class="reporte-tabla grupos-tabla">
                            <caption class="solo-lectores">Resumen por alumno según los filtros aplicados</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Alumno</th><th scope="col">Curso</th><th scope="col">Resumen</th>
                                    <th scope="col">Registros</th><th scope="col">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alumnos as $alumno)
                                    <tr data-alumno-id="{{ $alumno->id }}">
                                        <td>
                                            <p class="alumno-nombre">{{ $alumno->nombre_completo }}</p>
                                            <p class="dato-secundario">{{ $alumno->codigo }}</p>
                                        </td>
                                        <td>
                                            @foreach($cursosPorAlumno->get($alumno->id, collect()) as $vinculo)
                                                <div class="curso-alumno">
                                                    <p class="dato-principal">{{ $vinculo->curso->nombre ?? '—' }}</p>
                                                    @if($vinculo->curso)
                                                        <p class="dato-secundario">{{ $vinculo->curso->nivel }} · Sección {{ $vinculo->curso->seccion }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            <div class="resumen-alumno">
                                                <span class="estado-etiqueta estado-presente">Presentes: {{ $alumno->presentes }}</span>
                                                <span class="estado-etiqueta estado-ausente">Ausentes: {{ $alumno->ausentes }}</span>
                                                @if($alumno->permisos)
                                                    <span class="estado-etiqueta estado-permiso">Permisos: {{ $alumno->permisos }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="conteo-registros">{{ number_format($alumno->registros) }}</td>
                                        <td>
                                            <button type="button" class="reporte-boton" data-ver-asistencias
                                                aria-expanded="false" aria-controls="asistencias-alumno-{{ $alumno->id }}"
                                                data-url="{{ route('admin.asistencias.detalles', ['alumno' => $alumno->id] + $filtros) }}">Ver asistencias</button>
                                        </td>
                                    </tr>
                                    <tr id="asistencias-alumno-{{ $alumno->id }}" class="detalle-fila" hidden>
                                        <td colspan="5"><div class="detalle-panel" data-detalle-panel aria-live="polite"></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if($alumnos->hasPages())
                    <div class="listado-footer">{{ $alumnos->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</div>
@push('scripts')
    @vite('resources/js/admin/asistencias.js')
@endpush
</x-app-layout>
