<x-historial-mensualidades-layout>
    <x-boton-regreso :href="route('admin.mensualidades.historial.index')">Volver a Alumnos</x-boton-regreso>
    <h1>Historial de mensualidades</h1>
    <p>{{ $alumno->nombre_completo }} · {{ $alumno->codigo }}</p>
    <p class="hm-note"><strong>Curso asignado:</strong>
        @if($asignacionAmbigua) Hay varias inscripciones activas; requiere revisión.
        @elseif($cursoAsignado) {{ $cursoAsignado->nombre }} · {{ $cursoAsignado->nivel }} · {{ $cursoAsignado->seccion }} · {{ $cursoAsignado->anio_lectivo }}
        @else Sin curso activo asignado.
        @endif
    </p>
    @if($errors->any())<div role="alert" class="hm-warning">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="hm-panel hm-filters" method="GET" action="{{ route('admin.mensualidades.historial.show', $alumno) }}">
        <label>Año<input type="number" name="anio" min="1900" max="2100" value="{{ $anio }}" required></label>
        <button class="hm-btn" type="submit">Consultar</button>
    </form>
    <div class="hm-panel">
        <p><strong>Fecha registrada de solicitud/inscripción:</strong> {{ $referencia['fecha']?->format('d/m/Y') ?? 'No disponible' }}</p>
        <p class="hm-note">{{ $referencia['explicacion'] }}</p>
        <p class="hm-note">Se conservan todas las obligaciones del año. «Sin registro» no representa deuda.</p>
    </div>
    <dl class="hm-summary">
        @foreach(['pagado' => 'Importe marcado pagado', 'pendiente' => 'Saldo pendiente registrado', 'vencido' => 'Del pendiente: vencido'] as $clave => $titulo)
            <div><dt>{{ $titulo }}</dt><dd>${{ number_format($resumen[$clave] / 100, 2) }}</dd></div>
        @endforeach
    </dl>
    <p class="hm-note">El importe vencido ya está incluido en el pendiente.</p>
    @if($resumen['en_revision'])<p class="hm-warning" role="status">${{ number_format($resumen['en_revision'] / 100, 2) }} en registros con avisos. Los posibles duplicados quedan fuera de los indicadores hasta su revisión; los demás conservan su estado registrado.</p>@endif
    @if($meses->isEmpty())<p class="hm-note" role="status">No hay meses ni mensualidades para el año consultado dentro de la referencia disponible.</p>@endif
    <div class="hm-table"><table><caption class="sr-only">Todas las mensualidades del alumno en {{ $anio }}</caption>
        <thead><tr><th scope="col">Mes</th><th scope="col">Curso</th><th scope="col">Estado</th><th scope="col">Importe</th><th scope="col">Vencimiento</th><th scope="col">Fecha de pago / avisos</th></tr></thead>
        @foreach($meses as $mes)
            <tbody data-mes="{{ $mes['numero'] }}">
                @forelse($mes['filas'] as $fila)
                    <tr data-obligacion="{{ $fila['registro']->id }}">@if($loop->first)<th scope="rowgroup" rowspan="{{ $mes['filas']->count() }}">{{ $mes['nombre'] }}</th>@endif @include('admin.partials.mensualidad-historial-celdas')</tr>
                @empty<tr><th scope="row">{{ $mes['nombre'] }}</th><td colspan="5">Sin registro</td></tr>@endforelse
            </tbody>
        @endforeach
    </table></div>
    @if($sinMes->isNotEmpty())
        <h2>Registros con mes no reconocido</h2>
        <p class="hm-note">Se conservan sus importes en los totales por estado. No se asignan a un mes inventado.</p>
        <div class="hm-table"><table><thead><tr><th>Mes original</th><th>Curso</th><th>Estado</th><th>Importe</th><th>Vencimiento</th><th>Fecha de pago / avisos</th></tr></thead><tbody>
            @foreach($sinMes as $fila)<tr><th>{{ $fila['registro']->mes }}</th>@include('admin.partials.mensualidad-historial-celdas')</tr>@endforeach
        </tbody></table></div>
    @endif
</x-historial-mensualidades-layout>
