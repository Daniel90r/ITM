@php
    $busqueda = fn ($modulo) => route('admin.sugerencias.busqueda', $modulo);
    $configuraciones = match (request()->route()?->getName()) {
        'admin.alumnos' => [['selector' => '#buscarAlumno', 'url' => $busqueda('alumnos'), 'params' => ['curso' => '#filtrarCurso', 'anio' => '#filtrarAnio']]],
        'admin.maestros' => [['selector' => '#buscadorMaestro', 'url' => $busqueda('maestros')]],
        'admin.cursos' => [['selector' => '#buscadorCurso', 'url' => $busqueda('cursos'), 'params' => ['turno' => '#filtroTurno']]],
        'admin.materias' => [['selector' => '#buscadorMateria', 'url' => $busqueda('materias')]],
        'admin.solicitudes' => [['selector' => '#buscarSolicitud', 'url' => $busqueda('solicitudes'), 'submit' => true]],
        'admin.reportes.solicitudes' => [['selector' => '#buscarReporteSolicitud', 'url' => $busqueda('solicitudes'), 'submit' => true]],
        'admin.asistencias' => [['selector' => '#asistencia-busqueda', 'url' => route('admin.sugerencias.asistencias'), 'submit' => true]],
        'admin.mensualidades' => [['selector' => '#buscarAlumno', 'url' => route('admin.sugerencias.mensualidades'), 'submit' => true]],
        'admin.pagos' => [['selector' => '#pagos-alumno', 'url' => route('admin.pagos.alumnos'), 'submit' => true, 'selected' => 'alumno_id']],
        'admin.pagos.historial.index' => [['selector' => 'input[name=q]', 'url' => route('admin.sugerencias.estados-cuenta'), 'submit' => true]],
        'admin.encargados.index' => [['selector' => '#buscarEncargado', 'url' => route('admin.sugerencias.encargados'), 'submit' => true]],
        'admin.notas' => [
            ['selector' => '#buscadorCurso', 'url' => $busqueda('notas-cursos'), 'courseCallback' => true],
            ['selector' => '#buscadorAlumno', 'url' => $busqueda('notas-alumnos'), 'params' => ['curso' => '#buscadorCurso']],
        ],
        'admin.boletas' => [
            ['selector' => '#buscadorCurso', 'url' => $busqueda('boletas-cursos')],
            ['selector' => '#buscadorAlumno', 'url' => $busqueda('boletas-alumnos'), 'contextCourse' => true],
        ],
        default => [],
    };
@endphp
@if($configuraciones)
    @push('styles')
        <style>{!! file_get_contents(resource_path('css/filtros-autocompletado.css')) !!}</style>
    @endpush
    @push('scripts')
        <script>window.itmFiltrosAutocompletado = {{ Illuminate\Support\Js::from($configuraciones) }};</script>
        <script>{!! file_get_contents(resource_path('js/filtros-autocompletado.js')) !!}</script>
    @endpush
@endif
