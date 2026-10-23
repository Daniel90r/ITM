<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de asistencia</title>
    @include('admin.reportes.pdf.asistencia-estilos')
</head>
<body>
@php
    $anexo = $observaciones->count() > 3 || $observaciones->sum(fn ($o) => mb_strlen($o)) > 600;
    // Ajuste exclusivo de presentación: conservar nombres completos y sus totales.
    $paginasVisuales = $paginas->flatMap(function ($pagina) {
        $longitud = $pagina['alumnos']->max(fn ($a) => mb_strlen($a->nombre_completo)) ?? 0;
        $limite = $longitud > 120 ? 3 : ($longitud > 60 ? 6 : 10);
        return $pagina['alumnos']->isEmpty() ? [$pagina] : $pagina['alumnos']->chunk($limite)
            ->map(fn ($grupo) => ['fechas' => $pagina['fechas'], 'alumnos' => $grupo])->all();
    });
@endphp
@include('admin.reportes.pdf.asistencia-encabezado')
@foreach($paginasVisuales as $pagina)
    <div @if(!$loop->last || $anexo) class="pagina" @endif>
        @include('admin.reportes.pdf.asistencia-academica')
        <p class="nota leyenda"><strong>P</strong>: presente · <strong>A</strong>: ausente · <strong>PM</strong>: permiso · <strong>—</strong>: sin registro · <strong>?</strong>: requiere revisión. Solo los presentes suman asistencia.</p>
        <table class="matriz">
            <colgroup><col style="width:26%">
                @for($i = 0; $i < $pagina['fechas']->count() + 2; $i++)<col style="width:{{ 74 / ($pagina['fechas']->count() + 2) }}%">@endfor
            </colgroup>
            <thead><tr><th class="nombre" style="width:26%">NOMBRE DEL ALUMNO</th>
                @foreach($pagina['fechas'] as $fecha)<th style="width:{{ 74 / ($pagina['fechas']->count() + 2) }}%">{{ \Carbon\Carbon::parse($fecha)->format('d/m') }}<br>{{ substr($fecha, 0, 4) }}</th>@endforeach
                <th style="width:{{ 74 / ($pagina['fechas']->count() + 2) }}%">TOTAL<br>bloque</th><th style="width:{{ 74 / ($pagina['fechas']->count() + 2) }}%">TOTAL<br>período</th>
            </tr></thead>
            <tbody>
            @forelse($pagina['alumnos'] as $alumno)
                <tr><td class="nombre">{{ $alumno->nombre_completo }}</td>
                    @foreach($pagina['fechas'] as $fecha)<td>{{ $marcas->get($alumno->id.'|'.$fecha, '—') }}</td>@endforeach
                    <td>{{ $pagina['fechas']->filter(fn ($f) => $marcas->get($alumno->id.'|'.$f) === 'P')->count() }}</td><td>{{ $totalesAlumno[$alumno->id] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 3 + $pagina['fechas']->count() }}">No hay alumnos inscritos para los filtros seleccionados.</td></tr>
            @endforelse
            <tr class="total"><th class="nombre">TOTAL DE ASISTENCIAS<br>Alumnos de esta página</th>
                @foreach($pagina['fechas'] as $fecha)<td>{{ $pagina['alumnos']->filter(fn ($a) => $marcas->get($a->id.'|'.$fecha) === 'P')->count() }}</td>@endforeach
                <td>{{ $pagina['alumnos']->sum(fn ($a) => $pagina['fechas']->filter(fn ($f) => $marcas->get($a->id.'|'.$f) === 'P')->count()) }}</td><td>{{ $pagina['alumnos']->sum(fn ($a) => $totalesAlumno[$a->id]) }}</td>
            </tr>
            @if($alumnos->count() > 10)
                <tr class="total"><th class="nombre">TOTAL POR FECHA<br>Todos los alumnos</th>
                    @foreach($pagina['fechas'] as $fecha)<td>{{ $totalesFecha[$fecha] }}</td>@endforeach
                    <td>{{ $pagina['fechas']->sum(fn ($f) => $totalesFecha[$f]) }}</td><td>{{ $totalesAlumno->sum() }}</td>
                </tr>
            @endif
            </tbody>
        </table>
        <p class="nota"><strong>Total del período:</strong> {{ $totalesAlumno->sum() }} presentes · {{ $alumnos->count() }} alumnos · {{ $fechas->count() }} fechas registradas. El total del período se repite como referencia; no sumar entre páginas.</p>
        <p class="nota">Nómina con inscripción aprobada actual en esta materia. Las fechas corresponden a asistencias registradas; el horario mostrado es el actual del curso.@if(!empty($filtros['q'])) Consulta: {{ $filtros['q'] }}.@endif</p>
        @if($fechas->isEmpty())<p class="nota">No hay registros de asistencia para los filtros seleccionados. Un espacio sin registro no equivale a una ausencia.</p>@endif
        @if($duplicadas)<p class="nota advertencia">Hay {{ $duplicadas }} coincidencias de alumno y fecha con más de un registro. Se marcan con ? y no suman asistencia hasta su revisión.</p>@endif
        <h2>OBSERVACIONES</h2>
        <p class="nota">{{ $observaciones->isEmpty() ? 'Sin observaciones registradas.' : ($anexo ? 'Consultar el anexo de observaciones por alumno y fecha.' : ($loop->last ? 'Registradas por alumno y fecha:' : 'Consultar las observaciones al final del control.')) }}</p>
        @if(!$anexo && $loop->last)
            @foreach($observaciones as $observacion)<p class="observacion">{{ $observacion }}</p>@endforeach
        @endif
        <table class="firmas"><tr><td><div class="linea-firma">Firma del Director</div><span class="etiqueta">Nombre: ____________________________</span></td><td><div class="linea-firma">Firma del Instructor</div><span class="etiqueta">Nombre: ____________________________</span></td></tr></table>
    </div>
@endforeach
@if($anexo)
    <h2>ANEXO · OBSERVACIONES</h2>
    <p>{{ $detalle->curso->nombre }} · {{ $detalle->materia->nombre }}</p>
    @foreach($observaciones as $observacion)<p class="observacion">{{ $observacion }}</p>@endforeach
@endif
</body>
</html>
