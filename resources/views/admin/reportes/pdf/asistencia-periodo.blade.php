<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Reporte de asistencias {{ $filtros['modalidad'] }}</title>
@include('admin.reportes.pdf.asistencia-estilos')
</head><body>
@php
    $estadoAplicado = \App\Services\FiltrosHistoricosAsistencia::ESTADOS[$filtros['estado'] ?? ''] ?? 'Todos los estados';
    $anual = $filtros['modalidad'] === 'anual';
    $meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
@endphp
@include('admin.reportes.pdf.asistencia-encabezado')
@foreach($paginas as $pagina)
<div @if(!$loop->last || $observaciones->isNotEmpty()) class="pagina" @endif>
    <h2>HISTÓRICO {{ $anual ? 'ANUAL' : 'MENSUAL' }} · {{ $filtros['anio'] }}@if(!$anual) · {{ $meses[(int) $filtros['mes']] }}@endif · Estado: {{ $estadoAplicado }}</h2>
    @include('admin.reportes.pdf.asistencia-academica')
    <p class="nota leyenda">P = Presente · A = Ausente · PM = Permiso · — = Sin registro · ? = Registro duplicado, requiere revisión. @if($anual) Cada mes muestra cantidades P/A/PM. @endif Incluye alumnos con registros históricos, aunque ya no estén inscritos.</p>
    <table class="matriz">
        <colgroup><col style="width:{{ $anual ? 25 : 30 }}%">
        @foreach($pagina['columnas'] as $col)<col style="width:{{ ($anual ? 75 : 70) / ($pagina['columnas']->count()+3) }}%">@endforeach
        @for($i=0;$i<3;$i++)<col style="width:{{ ($anual ? 75 : 70) / ($pagina['columnas']->count()+3) }}%">@endfor
        </colgroup>
        <thead><tr><th class="nombre" style="width:{{ $anual ? 25 : 30 }}%">NOMBRE COMPLETO DEL ALUMNO</th>
        @foreach($pagina['columnas'] as $col)<th style="width:{{ ($anual ? 75 : 70) / ($pagina['columnas']->count()+3) }}%">{{ $anual ? $meses[$col] : \Carbon\Carbon::parse($col)->format('d/m') }}</th>@endforeach
        @foreach(['P','A','PM'] as $estadoTotal)<th style="width:{{ ($anual ? 75 : 70) / ($pagina['columnas']->count()+3) }}%">Total {{ $estadoTotal }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($pagina['alumnos'] as $alumno)
            <tr><td class="nombre">{{ $alumno->nombre_completo }}</td>
            @foreach($pagina['columnas'] as $col)
                <td>@if($anual)
                    @php
                        $cantidades = $porMes[$alumno->id][$col];
                        $duplicadoMes = $duplicados->contains(fn ($g) => $g->first()->alumno_id === $alumno->id && (int) $g->first()->fecha->format('n') === $col);
                    @endphp
                    {{ array_sum($cantidades) ? implode('/', $cantidades) : ($duplicadoMes ? '?' : 'Sin registro') }}@if($duplicadoMes && array_sum($cantidades)) ?@endif
                @else{{ $marcas->get($alumno->id.'|'.$col, '—') }}@endif</td>
            @endforeach
            @foreach($porAlumno[$alumno->id] as $cantidad)<td>{{ $cantidad }}</td>@endforeach
            </tr>
        @empty<tr><td colspan="{{ $pagina['columnas']->count()+4 }}">No hay registros para los filtros seleccionados.</td></tr>@endforelse
        </tbody>
    </table>
    <p class="nota"><strong>Totales del período:</strong> P: {{ $totales['presente'] }} · A: {{ $totales['ausente'] }} · PM: {{ $totales['permiso'] }} · Alumnos: {{ $alumnos->count() }} · Fechas distintas registradas: {{ $fechas->count() }}. Los totales se repiten como referencia; no sumarlos entre páginas o bloques.</p>
    @if($anual)
        <p class="nota"><strong>Fechas registradas por mes:</strong> @foreach($meses as $numero=>$mes){{ $mes }}: {{ $fechasMes[$numero] }}{{ $loop->last ? '.' : ' · ' }}@endforeach</p>
        <p class="nota">Para consultar las fechas y marcas individuales, genera el reporte mensual del mismo curso, materia, año y estado. Los totales anuales son la suma de los mensuales.</p>
    @endif
    <p class="nota">Solo se cuentan registros reales del estado seleccionado. No se generan clases a partir del horario actual ni ausencias por falta de registro. Las fechas registradas no equivalen al total de presentes.</p>
    @if($duplicados->isNotEmpty())<p class="nota advertencia">{{ $duplicados->count() }} coincidencias de alumno, materia y fecha requieren revisión. Todos los registros de esas celdas quedan excluidos de los totales, incluso si tienen estados distintos.</p>@endif
    <h2>OBSERVACIONES</h2><p class="nota">{{ $observaciones->isEmpty() ? 'Sin observaciones registradas.' : 'Consultar el anexo de observaciones por alumno y fecha.' }}</p>
    <table class="firmas"><tr><td><div class="linea-firma">Firma del Director</div></td><td><div class="linea-firma">Firma del Instructor</div></td></tr></table>
</div>
@endforeach
@if($observaciones->isNotEmpty())
<h2>ANEXO · OBSERVACIONES</h2>
<p>{{ $detalle->curso->nombre }} · {{ $detalle->materia->nombre }} · {{ $estadoAplicado }}</p>
@foreach($observaciones as $observacion)<p class="observacion">{{ $observacion }}</p>@endforeach
@endif
</body></html>
