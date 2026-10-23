<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de inscripciones</title>
    <style>
        @page { margin: 39.6pt 43.2pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }
        .cabecera { position: relative; min-height: 96pt; text-align: center; }
        .cabecera img { position: absolute; top: 0; }
        .itm { left: -12pt; width: 89.4pt; height: 91.2pt; }
        .escudo { right: -13pt; width: 83.4pt; height: 79.4pt; }
        h1 { font-size: 19pt; margin: 0 76pt 8pt; }
        .subtitulo { font-size: 10pt; margin: 0 82pt; }
        h2 { font-size: 12pt; margin: 14pt 0 8pt; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border: .5pt solid #000; padding: 6pt 3pt; word-wrap: break-word; vertical-align: middle; }
        th { font-weight: bold; }
        .metadatos th { font-size: 8pt; }
        .metadatos td { font-size: 8pt; text-align: center; }
        .resumen th { font-size: 10pt; }
        .resumen td { text-align: center; font-weight: bold; }
        .detalle { font-size: 7pt; }
        .detalle th { font-size: 7pt; text-align: center; }
        .detalle td { white-space: pre-line; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .nota { font-size: 7pt; line-height: 1.4; margin: 6pt 0; }
        .metadatos th, .metadatos td, .resumen th, .resumen td { width: 25%; }
        .detalle th, .detalle td { width: 12.5%; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
        <h1>REPORTE DE INSCRIPCIONES</h1>
        <p class="subtitulo">Seguimiento de solicitudes: pendientes, aprobadas y rechazadas</p>
        <img class="escudo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-inscripciones.jpg'))) }}" alt="Escudo de El Salvador">
    </div>
    <table class="metadatos">
        <tr><th>PERÍODO</th><th>FECHA DEL REPORTE</th><th>RESPONSABLE</th><th>DESTINATARIO</th></tr>
        <tr><td>Todo el historial</td><td>{{ $emitido->format('d/m/Y H:i') }}</td><td>{{ $responsable ?: 'No registrado' }}</td><td>Dirección</td></tr>
    </table>
    <p class="nota">Filtros: estado {{ $filtros['estado'] ?? 'todos' }}; búsqueda {{ trim($filtros['q'] ?? '') ?: 'sin restricción' }}.</p>
    <h2>RESUMEN PARA DIRECCIÓN</h2>
    <table class="resumen">
        <tr><th>TOTAL DE INSCRIPCIONES</th><th>PENDIENTES</th><th>APROBADAS</th><th>RECHAZADAS</th></tr>
        <tr><td>{{ $total }}</td><td>{{ $pendientes }}</td><td>{{ $aprobadas }}</td><td>{{ $rechazadas }}</td></tr>
    </table>
    <p class="nota">Total de registros filtrados, incluidas solicitudes e inscripciones aprobadas. Solo las aprobadas constituyen inscripciones efectivas.
        @if($inactivas || $otros) Incluidos además: {{ $inactivas }} inactivas y {{ $otros }} de otros estados. @endif
    </p>
    <h2>DETALLE DE INSCRIPCIONES</h2>
    <table class="detalle">
        <thead><tr><th>N.º</th><th>ALUMNO</th><th>CURSO</th><th>FECHA DE INSCRIPCIÓN</th><th>ESTADO</th><th>RESPONSABLE</th><th>MOTIVO / OBSERVACIÓN</th><th>ACCIÓN</th></tr></thead>
        <tbody>
        @forelse($detalle as $renglon)
            <tr>@foreach($renglon as $celda)<td>{{ $celda }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="8">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="nota">El responsable del encabezado genera el informe. El detalle identifica al administrador únicamente en reactivaciones registradas; no existe auditoría de responsables de aprobación o rechazo. El estado actual no acredita una acción histórica.</p>
</body>
</html>
