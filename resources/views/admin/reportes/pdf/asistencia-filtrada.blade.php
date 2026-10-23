<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias históricas · {{ $estadoAplicado }}</title>
    @include('admin.reportes.pdf.asistencia-estilos')
</head>
<body>
@include('admin.reportes.pdf.asistencia-encabezado')
@foreach($paginas as $grupo)
    <div @if(!$loop->last || $observaciones->isNotEmpty()) class="pagina" @endif>
        @include('admin.reportes.pdf.asistencia-academica')
        <p class="nota advertencia"><strong>Histórico filtrado · Estado: {{ $estadoAplicado }}.</strong> Solo registros que coinciden con los filtros, aunque el alumno ya no esté inscrito. Este documento no es el control completo del curso.</p>
        <table class="matriz">
            <thead><tr><th style="width:12%">FECHA</th><th style="width:28%">ALUMNO</th><th style="width:23%">MATERIA</th><th style="width:12%">ESTADO</th><th style="width:25%">REGISTRADO POR</th></tr></thead>
            <tbody>
                @forelse($grupo as $registro)
                    <tr><td>{{ $registro->fecha->format('d/m/Y') }}</td><td class="nombre">{{ $registro->alumno->nombre_completo }}</td><td class="nombre">{{ $detalle->materia->nombre }}</td><td>{{ $estadoAplicado }}</td><td class="nombre">{{ $registro->maestro?->nombre_completo ?? 'No registrado' }}</td></tr>
                @empty
                    <tr><td colspan="5">No hay registros para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="nota"><strong>Total de registros incluidos: {{ $registros->count() }}</strong> · Presentes: {{ $totales->get('presente', 0) }} · Ausentes: {{ $totales->get('ausente', 0) }} · Permisos: {{ $totales->get('permiso', 0) }}. Totales del documento; no sumar entre páginas.</p>
        <p class="nota">No se generan fechas sin registro. El horario mostrado es la configuración actual del curso.@if(!empty($filtros['q'])) Consulta: {{ $filtros['q'] }}.@endif</p>
        @if($observaciones->isNotEmpty())<p class="nota">Consultar el anexo de observaciones por alumno y fecha.</p>@endif
        <table class="firmas"><tr><td><div class="linea-firma">Firma del Director</div><span class="etiqueta">Nombre: ____________________________</span></td><td><div class="linea-firma">Firma del Instructor</div><span class="etiqueta">Nombre: ____________________________</span></td></tr></table>
    </div>
@endforeach
@if($observaciones->isNotEmpty())
    <h2>ANEXO · OBSERVACIONES · {{ $estadoAplicado }}</h2>
    <p>{{ $detalle->curso->nombre }} · {{ $detalle->materia->nombre }}</p>
    @foreach($observaciones as $observacion)<p class="observacion">{{ $observacion }}</p>@endforeach
@endif
</body>
</html>
