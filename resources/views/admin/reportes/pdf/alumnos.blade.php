<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de alumnos inscritos</title>
    <style>
        @page{margin:94pt 30pt 30pt}body{font-family:DejaVu Sans,sans-serif;font-size:8pt;color:#1e293b;line-height:11pt}
        header{position:fixed;top:-76pt;left:0;right:0;height:64pt;border-bottom:1pt solid #233c5b;text-align:center}header img{position:absolute;top:0;width:54pt;height:auto}.itm{left:0}.escudo{right:0}
        .institucion{margin:0 65pt;font-weight:bold;font-size:10pt}h1{font-size:16pt;line-height:20pt;margin:12pt 65pt 0}.pie{position:fixed;bottom:-17pt;border-top:.5pt solid #aab3bd;width:100%;padding-top:4pt;font-size:7pt}
        table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:.5pt solid #94a3b8;padding:5pt;word-wrap:break-word;vertical-align:top}th{background:#edf2f7;color:#233c5b}thead{display:table-header-group}tr{page-break-inside:avoid}.metadatos td{width:25%;font-size:7pt}.nota{font-size:7pt;line-height:10pt;margin:8pt 0}.detalle{font-size:7pt}.detalle td{line-height:10pt}
    </style>
</head>
<body>
    <header>
        <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
        <p class="institucion">Instituto de Computación de Aguilares</p>
        <h1>REPORTE DE ALUMNOS INSCRITOS</h1>
        <img class="escudo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-inscripciones.jpg'))) }}" alt="Escudo de El Salvador">
    </header>
    <div class="pie">ITM Aguilares · Alumnos inscritos</div>
    <table class="metadatos"><tr><td><strong>PERÍODO CONSULTADO</strong><br>{{ isset($filtros['anio']) ? (isset($filtros['mes']) ? \App\Services\ReportesAdministrativos::MESES[(int) $filtros['mes']].' / ' : 'Año ').$filtros['anio'] : 'Todo el historial' }}</td><td><strong>EMISIÓN</strong><br>{{ $emitido->format('d/m/Y H:i') }}</td><td><strong>RESPONSABLE DEL INFORME</strong><br>{{ $responsable }}</td><td><strong>DESTINATARIO</strong><br>Dirección</td></tr></table>
    <p class="nota">Curso: {{ $cursoFiltro ? $cursoFiltro->nombre.' · '.$cursoFiltro->nivel.' · '.$cursoFiltro->seccion.' · '.$cursoFiltro->anio_lectivo : 'Todos' }} · Estado del alumno: {{ isset($filtros['estado']) ? ucfirst($filtros['estado']) : 'Todos' }}.</p>
    <p class="nota">Solo inscripciones actualmente aprobadas. El período se aplica a la <strong>fecha registrada de solicitud/inscripción</strong>; no acredita cuándo se aprobó la inscripción ni reconstruye la matrícula histórica de ese mes.</p>
    <p><strong>Alumnos únicos: {{ $totalAlumnos }}</strong></p>
    <table class="detalle"><thead><tr><th style="width:10%">CÓDIGO</th><th style="width:20%">ALUMNO</th><th style="width:29%">CURSOS APROBADOS DEL PERÍODO</th><th style="width:10%">INSCRIPCIÓN</th><th style="width:10%">ESTADO DEL ALUMNO</th><th style="width:21%">FECHA REGISTRADA DE SOLICITUD/INSCRIPCIÓN</th></tr></thead><tbody>
        @forelse($filas as $fila)<tr>@foreach($fila as $valor)<td>{{ $valor === null || $valor === '' ? '—' : $valor }}</td>@endforeach</tr>
        @empty<tr><td colspan="6">No hay registros para los filtros seleccionados.</td></tr>@endforelse
    </tbody></table>
</body>
</html>
