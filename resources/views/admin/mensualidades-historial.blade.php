<x-historial-mensualidades-layout>
    <x-boton-regreso :href="route('admin.pagos.historial.index')">Volver a Estados de cuenta</x-boton-regreso>
    <h1 style="overflow-wrap:anywhere">Estado de cuenta de {{ $alumno->nombre_completo }}</h1>
    <p class="hm-note"><strong>Código institucional:</strong> {{ $alumno->codigo }}</p>
    <p class="hm-note"><strong>Curso de la inscripción activa actual:</strong>
        @if($asignacionAmbigua) Hay varias inscripciones activas; requiere revisión.
        @elseif($cursoAsignado) {{ $cursoAsignado->nombre }} · {{ $cursoAsignado->nivel }} · {{ $cursoAsignado->seccion }} · {{ $cursoAsignado->anio_lectivo }}
        @else Sin curso activo asignado.
        @endif
    </p>
    @if($errors->any())<div role="alert" class="hm-warning">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="hm-panel hm-filters" method="GET" action="{{ route('admin.pagos.estado-cuenta', $alumno) }}">
        <label>Año del estado de cuenta<input type="number" name="anio" min="1900" max="2100" value="{{ $anio }}" required></label>
        <button class="hm-btn" type="submit">Consultar año</button>
    </form>
    <div class="hm-panel">
        <p><strong>Fecha de referencia del registro de inscripción:</strong> {{ $referencia['fecha']?->format('d/m/Y') ?? 'No disponible' }}</p>
        <p class="hm-note">{{ str_replace('solicitud/inscripción', 'registro de inscripción', $referencia['explicacion']) }}</p>
        <p class="hm-note">Se conservan las mensualidades del año, incluso de cursos anteriores. «Sin mensualidad registrada» no representa una deuda.</p>
    </div>
    <dl class="hm-summary">
        <div><dt>Total pagado durante {{ $anio }}</dt><dd>${{ number_format($totalPagos, 2) }}</dd></div>
        @foreach(['pendiente' => 'Total pendiente de pago', 'vencido' => 'Importe vencido'] as $clave => $titulo)
            <div><dt>{{ $titulo }}</dt><dd>${{ number_format($resumen[$clave] / 100, 2) }}</dd></div>
        @endforeach
    </dl>
    <p class="hm-note">El importe vencido está incluido en el total pendiente de pago. «Vencido» identifica mensualidades pendientes cuya fecha de vencimiento ya pasó; las demás conservan el estado «Pendiente».</p>
    <p class="hm-note">Mensualidades de {{ $anio }} registradas como pagadas: ${{ number_format($resumen['pagado'] / 100, 2) }}. Corresponde al año de la mensualidad; el total pagado durante el año se calcula por la fecha real de pago. Los registros con inconsistencias conservan sus avisos.</p>
    @if(session('success'))<p class="hm-panel" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="hm-warning" role="alert">{{ session('error') }}</p>@endif
    <h2>Mensualidades del alumno · {{ $anio }}</h2>
    @if($resumen['en_revision'])<p class="hm-warning" role="status">${{ number_format($resumen['en_revision'] / 100, 2) }} en registros con avisos. Los posibles duplicados quedan fuera de los indicadores hasta su revisión; los demás conservan su estado registrado.</p>@endif
    @if($meses->isEmpty())<p class="hm-note" role="status">No hay meses ni mensualidades para el año consultado dentro de la referencia disponible.</p>@endif
    <div class="hm-table"><table><caption class="sr-only">Todas las mensualidades del alumno en {{ $anio }}</caption>
        <thead><tr><th scope="col">Mes</th><th scope="col">Curso</th><th scope="col">Estado</th><th scope="col">Importe</th><th scope="col">Vencimiento</th><th scope="col">Fecha de pago / avisos</th></tr></thead>
        @foreach($meses as $mes)
            <tbody data-mes="{{ $mes['numero'] }}">
                @forelse($mes['filas'] as $fila)
                    <tr data-obligacion="{{ $fila['registro']->id }}">@if($loop->first)<th scope="rowgroup" rowspan="{{ $mes['filas']->count() }}">{{ $mes['nombre'] }}</th>@endif @include('admin.partials.mensualidad-historial-celdas')</tr>
                @empty<tr><th scope="row">{{ $mes['nombre'] }}</th><td colspan="5">Sin mensualidad registrada</td></tr>@endforelse
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
    <h2>Pagos realizados durante {{ $anio }}</h2>
    <p class="hm-note">Se muestran los pagos según su fecha real de pago. El período de la mensualidad puede corresponder a otro año.</p>
    <a class="hm-btn hm-btn-secondary" href="{{ route('admin.pagos', ['alumno_id' => $alumno->id]) }}">Ver pagos realizados de todos los años</a>
    @include('admin.partials.pagos-movimientos')
</x-historial-mensualidades-layout>
