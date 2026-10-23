<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Maestros y Asignaciones</title>
    <style>
        @page { margin: 43.2pt 46.8pt 39.6pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }
        .cabecera { position: relative; min-height: 100pt; text-align: center; }
        .cabecera img { position: absolute; top: 0; }
        .itm { left: -14pt; width: 88pt; height: 88pt; }
        .escudo { right: -14pt; width: 83pt; height: 79pt; }
        h1 { font-size: 16pt; margin: 10pt 76pt 8pt; }
        .subtitulo { font-size: 10pt; margin: 0 78pt; }
        h2 { font-size: 10pt; margin: 14pt 0 8pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: .5pt solid #000; padding: 7pt 4pt; word-wrap: break-word; vertical-align: middle; }
        .periodo { width: 345.6pt; margin: 0 auto; }
        .periodo td { width: 50%; background: #f3f6f9; }
        .etiqueta { font-size: 7pt; font-weight: bold; }
        .detalle { font-size: 8pt; }
        .detalle th { text-align: center; }
        .detalle td { white-space: pre-line; }
        .detalle tbody tr:nth-child(even) { background: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .resumen { page-break-inside: avoid; }
        .resumen h2 { font-size: 9pt; }
        .resumen td { width: 33.33%; font-size: 7pt; font-weight: bold; }
        .nota { font-size: 7pt; line-height: 1.4; margin: 7pt 0; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
        <h1>REPORTE DE MAESTROS Y ASIGNACIONES</h1>
        <p class="subtitulo">Control de docentes, cursos asignados y estado de asignación</p>
        <img class="escudo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-inscripciones.jpg'))) }}" alt="Escudo de El Salvador">
    </div>
    <table class="periodo"><tr>
        <td><span class="etiqueta">PERÍODO</span><br>AÑO: {{ $anios->implode(', ') ?: 'No disponible' }}<br>CICLO: No registrado</td>
        <td><span class="etiqueta">FECHA DEL REPORTE</span><br>{{ $emitido->format('d/m/Y H:i') }}</td>
    </tr></table>
    <p class="nota">Asignaciones activas registradas, sin restricción temporal. Años lectivos de los cursos incluidos.
        Filtros: estado del docente {{ $filtros['estado'] ?? 'todos' }}@if(isset($filtros['curso_id'])); curso ID {{ $filtros['curso_id'] }}@endif.
    </p>
    <h2>TABLA DE MAESTROS Y ASIGNACIONES</h2>
    <table class="detalle">
        <thead><tr><th style="width:30.6%">MAESTRO</th><th style="width:17.4%">CURSO ASIGNADO<br>Y MATERIA</th><th style="width:17.33%">ESTADO</th><th style="width:17.33%">FECHA ASIGNACIÓN</th><th style="width:17.34%">OBSERVACIONES</th></tr></thead>
        <tbody>
        @forelse($detalle as $fila)
            <tr>@foreach($fila as $valor)<td>{{ $valor }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="5">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="resumen">
        <h2>RESUMEN DE ASIGNACIONES</h2>
        <table><tr><td>DOCENTES REGISTRADOS<br>{{ $totalDocentes }}</td><td>ASIGNACIONES ACTIVAS<br>{{ $totalActivas }}</td><td>ASIGNACIONES PENDIENTES<br>No definido</td></tr></table>
    </div>
    <p class="nota">Totales del conjunto filtrado: docentes únicos y registros de asignación con estado activa. Los docentes sin asignación no se cuentan como pendientes. El sistema distingue activa, programada y finalizada; no define pendiente. Las programadas y finalizadas se conservan como historial y no se incluyen en este informe de asignaciones activas.</p>
    <p class="nota">Fecha de asignación: inicio registrado (fecha_inicio); — indica que no consta. No se sustituye por la fecha de creación o modificación. No existe un campo de ciclo académico.</p>
</body>
</html>
