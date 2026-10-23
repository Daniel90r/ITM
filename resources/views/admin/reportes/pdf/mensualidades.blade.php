<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de pagos y mensualidades</title>
    <style>
        @page { margin: 43.2pt 46.8pt 39.6pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }
        .cabecera { position: relative; min-height: 110pt; text-align: center; }
        .cabecera img { position: absolute; top: 0; }
        .itm { left: -14pt; width: 88pt; height: 88pt; }
        .escudo { right: -14pt; width: 83pt; height: 79pt; }
        h1 { font-size: 21pt; margin: 10pt 76pt 8pt; }
        .subtitulo { font-family: DejaVu Serif, serif; font-size: 10pt; margin: 0 78pt; }
        h2 { font-size: 11pt; margin: 14pt 0 8pt; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border: .5pt solid #000; padding: 7pt 4pt; vertical-align: middle; word-wrap: break-word; }
        .metadatos th, .metadatos td, .resumen th, .resumen td { width: 25%; text-align: center; }
        .metadatos, .resumen { font-size: 8pt; }
        .detalle { font-size: 8pt; }
        .detalle th, .detalle td { width: 14.285%; }
        .detalle th { text-align: center; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .nota { font-size: 7pt; line-height: 1.4; margin: 7pt 0; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
        <h1>ESTADO DE PAGOS / MENSUALIDADES</h1>
        <p class="subtitulo">Seguimiento de mensualidades</p>
        <img class="escudo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-inscripciones.jpg'))) }}" alt="Escudo de El Salvador">
    </div>
    <table class="metadatos">
        <tr><th>PERÍODO</th><th>FECHA DEL REPORTE</th><th>RESPONSABLE</th><th>DESTINATARIO</th></tr>
        <tr><td>{{ isset($filtros['mes']) ? \App\Services\ReportesAdministrativos::MESES[(int) $filtros['mes']] : 'Todos los meses' }} / {{ $filtros['anio'] ?? 'Todos los años' }}</td><td>{{ $emitido->format('d/m/Y H:i') }}</td><td>{{ $responsable ?: 'No registrado' }}</td><td>Dirección</td></tr>
    </table>
    <p class="nota">Filtros: estado {{ $filtros['estado'] ?? 'todos' }}@if(isset($filtros['curso_id'])); curso ID {{ $filtros['curso_id'] }}@endif.</p>
    <h2>RESUMEN</h2>
    <table class="resumen">
        <tr><th>ALUMNOS EVALUADOS</th><th>PAGOS REGISTRADOS</th><th>PAGOS PENDIENTES</th><th>MONTO PENDIENTE</th></tr>
        <tr><td>{{ $totalAlumnos }}</td><td>{{ $pagosRegistrados }}</td><td>{{ $pendientes }}</td><td>${{ number_format($pendienteCentavos / 100, 2) }}</td></tr>
    </table>
    <p class="nota">Unidades: alumnos únicos; pagos existentes asociados a mensualidades Pagado; cantidad de mensualidades pendientes; monto pendiente en dólares estadounidenses.</p>
    <h2>DETALLE DE ESTADO DE PAGOS</h2>
    <table class="detalle">
        <thead><tr><th>N.º</th><th>ALUMNO</th><th>CURSO</th><th>PERÍODO</th><th>PAGADO</th><th>PENDIENTE</th><th>ESTADO</th></tr></thead>
        <tbody>
        @forelse($filas as $fila)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $fila->nombre }} {{ $fila->apellido }}</td><td>{{ $fila->curso }} · {{ $fila->seccion }} · {{ $fila->anio_lectivo }}</td><td>{{ $fila->mes }} / {{ $fila->anio }}</td><td>${{ number_format($fila->pagadoCentavos / 100, 2) }}</td><td>${{ number_format($fila->pendienteCentavos / 100, 2) }}</td><td>{{ $fila->estado }}</td></tr>
        @empty
            <tr><td colspan="7">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="nota">Una fila por mensualidad. Importes según su estado actual, igual que la pantalla: total marcado pagado ${{ number_format($pagadoCentavos / 100, 2) }}; pendiente ${{ number_format($pendienteCentavos / 100, 2) }}. No representa conciliación de caja. Las reversiones eliminan el pago y restituyen la deuda; no se cuentan como pagos vigentes. El sistema no administra pagos parciales.</p>
    @if($incidencias)
        <p class="nota">Revisar {{ $incidencias }} mensualidad(es) con discrepancia entre estado y registro de pago (ausente, residual o monto diferente). Los importes reflejan el estado guardado, sin corregir datos ni inferir pagos.</p>
    @endif
</body>
</html>
