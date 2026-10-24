<x-historial-mensualidades-layout>
    <x-boton-regreso :href="route('admin.pagos')">Volver a Pagos realizados</x-boton-regreso>
    <h1>Estados de cuenta</h1>
    <p class="hm-note">Consulta las mensualidades y los pagos de alumnos activos o retirados. Consultar no genera cargos.</p>
    <form class="hm-panel hm-filters" method="GET" action="{{ route('admin.pagos.historial.index') }}">
        @if(!empty($filtros['anio']))<input type="hidden" name="anio" value="{{ $filtros['anio'] }}">@endif
        <label>Nombre o código<input name="q" value="{{ $q }}" maxlength="100" type="search"></label>
        <button class="hm-btn" type="submit">Buscar alumno</button>
        <a href="{{ route('admin.pagos.historial.index') }}">Limpiar</a>
    </form>
    <div class="hm-table"><table><caption class="sr-only">Alumnos disponibles para consulta histórica</caption><thead><tr><th>Código</th><th>Alumno</th><th>Estado del alumno</th><th>Consulta</th></tr></thead><tbody>
        @forelse($alumnos as $alumno)
            <tr><td>{{ $alumno->codigo }}</td><td>{{ $alumno->nombre_completo }}</td><td>{{ $alumno->activo ? 'Activo' : 'Inactivo' }}</td><td><a class="hm-btn" href="{{ route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id] + (!empty($filtros['anio']) ? ['anio' => $filtros['anio']] : [])) }}">Ver estado de cuenta<span class="sr-only"> de {{ $alumno->nombre_completo }}</span></a></td></tr>
        @empty<tr><td colspan="4">No hay alumnos para la búsqueda seleccionada.</td></tr>@endforelse
    </tbody></table></div>
    {{ $alumnos->links() }}
</x-historial-mensualidades-layout>
