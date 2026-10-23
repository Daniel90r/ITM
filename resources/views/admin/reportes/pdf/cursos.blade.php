<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ ($filtros['modalidad'] ?? '') === 'anual' ? 'Reporte anual de cursos' : 'Reporte de cursos' }}</title>
    <style>
        @page { margin: 43.2pt 46.8pt 39.6pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }
        .cabecera { position: relative; height: 96pt; text-align: center; }
        .cabecera img { position: absolute; top: 0; object-fit: contain; }
        .itm { left: -28pt; width: 93.6pt; height: 88.8pt; }
        .escudo { right: -22pt; width: 82.2pt; height: 81pt; }
        h1 { font-size: 22pt; margin: 12pt 60pt 0; }
        h2 { font-size: 11pt; margin: 14pt 0 7pt; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        th, td { border: .5pt solid #000; padding: 7pt 4pt; vertical-align: middle; overflow-wrap: break-word; }
        .periodo { width: 345.6pt; margin: 0 auto; }
        .periodo td { width: 50%; background: #f3f6f9; }
        .etiqueta { font-size: 7pt; font-weight: bold; }
        .detalle { font-size: 8pt; text-align: center; }
        .detalle th { font-size: 8pt; }
        .detalle tbody tr:nth-child(even) { background: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .resumen { page-break-inside: avoid; }
        .resumen td { width: 33.33%; font-size: 8pt; font-weight: bold; }
        .filtros { font-size: 8pt; margin: 7pt 0; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
        <h1>{{ ($filtros['modalidad'] ?? '') === 'anual' ? 'REPORTE ANUAL DE CURSOS' : 'REPORTE DE CURSOS' }}</h1>
        <img class="escudo" src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-cursos.png'))) }}" alt="Escudo de El Salvador">
    </div>
    <table class="periodo"><tr>
        <td><span class="etiqueta">{{ ($filtros['modalidad'] ?? '') === 'anual' ? 'AÑO CONSULTADO' : 'PERÍODO' }}</span><br><strong>AÑO: {{ $filtros['anio'] ?? 'Todos' }}@if(($filtros['modalidad'] ?? '') !== 'anual') &nbsp; MES: {{ isset($filtros['mes']) ? \App\Services\ReportesAdministrativos::MESES[(int)$filtros['mes']] : 'Todos' }}@endif</strong></td>
        <td><span class="etiqueta">FECHA DEL REPORTE</span><br>{{ $emitido->format('d/m/Y H:i') }}</td>
    </tr></table>
    @if(isset($filtros['curso_id']) || isset($filtros['estado']))
        <p class="filtros">Filtros:
            @if(isset($filtros['curso_id'])) Curso: {{ $filas->first()?->nombre ?? 'Sin resultados' }}. @endif
            @if(isset($filtros['estado'])) Estado actual: {{ $filtros['estado'] }}. @endif
        </p>
    @endif
    @if(!empty($filtros['modalidad']))
        <p class="filtros"><strong>MODALIDAD {{ strtoupper($filtros['modalidad']) }}.</strong> Cursos con inscripciones actualmente aprobadas cuya fecha registrada de solicitud/inscripción está en el {{ $filtros['modalidad'] === 'anual' ? 'año' : 'período' }}. No es la fecha de aprobación. Estado, visibilidad y cupos son actuales, no históricos.</p>
        <table class="detalle"><thead><tr><th style="width:24%">CURSO</th><th style="width:13%">ESTADO ACTUAL</th><th style="width:11%">VISIBILIDAD ACTUAL</th><th style="width:16%">APROBADAS CON FECHA EN EL {{ $filtros['modalidad'] === 'anual' ? 'AÑO' : 'PERÍODO' }}</th><th style="width:12%">APROBADAS ACTUALES</th><th style="width:12%">CUPO MÁXIMO ACTUAL</th><th style="width:12%">DISPONIBLES ACTUALES</th></tr></thead><tbody>
        @forelse($filas as $curso)<tr><td>{{ $curso->nombre }}<br>{{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</td><td>{{ $curso->estado_curso }}</td><td>{{ $curso->activo ? 'Visible' : 'Oculto' }}</td><td>{{ $curso->periodo_count }}</td><td>{{ $curso->aprobadas_count }}</td><td>{{ \App\Models\Curso::CUPOS_MAXIMO }}</td><td>{{ max(0,\App\Models\Curso::CUPOS_MAXIMO-$curso->aprobadas_count) }}</td></tr>
        @empty<tr><td colspan="7">No hay cursos con inscripciones actualmente aprobadas cuya fecha registrada corresponda al {{ $filtros['modalidad'] === 'anual' ? 'año' : 'período' }} seleccionado.</td></tr>@endforelse
        </tbody></table>
        <h2>RESUMEN DEL {{ $filtros['modalidad'] === 'anual' ? 'AÑO' : 'PERÍODO' }}</h2><p class="filtros">Cursos: {{ $totalCursos }} · Aprobadas con fecha en el {{ $filtros['modalidad'] === 'anual' ? 'año' : 'período' }}: {{ $totalPeriodo }} · Aprobadas actuales: {{ $totalInscritos }} · Cupos disponibles actuales: {{ $totalDisponibles }}.</p>
    @else
    <h2>TABLA DE CURSOS</h2>
    <table class="detalle">
        <thead><tr>
            <th style="width:19%">CURSO</th><th style="width:21%">ESTADO DEL CURSO</th>
            <th style="width:18%">FECHA DE INICIO</th><th style="width:19%">FECHA DE FINALIZACIÓN</th>
            <th style="width:23%">INSCRITOS / CUPOS DISPONIBLES</th>
        </tr></thead>
        <tbody>
        @forelse($filas as $curso)
            <tr>
                <td>{{ $curso->nombre }}<br>{{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</td>
                <td>{{ $curso->estado_curso }}</td>
                <td>{{ $curso->fecha_inicio?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $curso->fecha_fin?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $curso->aprobadas_count }} / {{ max(0, \App\Models\Curso::CUPOS_MAXIMO - $curso->aprobadas_count) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="resumen">
        <h2>RESUMEN</h2>
        <table><tr>
            <td>TOTAL DE CURSOS {{ $totalCursos }}</td>
            <td>TOTAL INSCRITOS {{ $totalInscritos }}</td>
            <td>TOTAL CUPOS DISPONIBLES {{ $totalDisponibles }}</td>
        </tr></table>
    </div>
    @endif
</body>
</html>
