<x-admin-reportes-layout>
    <x-slot:encabezado>
        <x-boton-regreso :href="route('admin.reportes')">Volver a Reportes</x-boton-regreso>
        <h1 class="text-2xl font-bold mt-2">Reporte de solicitudes de inscripción</h1>
    </x-slot:encabezado>
    <p class="text-sm text-gray-500 mt-3">Consulta y exporta las solicitudes de todo el historial. Solo las aprobadas constituyen inscripciones efectivas.</p>
    @if($errors->any())<div role="alert" class="p-4 rounded-lg bg-red-50 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.reportes.solicitudes') }}" class="reportes-filters mt-6" id="filtros-reporte-solicitudes">
        <label for="estado-reporte-solicitudes">Estado de inscripción
            <select id="estado-reporte-solicitudes" name="estado">
                @foreach(['todos' => 'Todos los estados', 'pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'inactiva' => 'Inactiva', 'otros' => 'Otros estados'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['estado'] ?? 'todos') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </label>
        <label for="buscarReporteSolicitud">Alumno o código
            <input id="buscarReporteSolicitud" name="q" type="search" maxlength="100" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre o código">
        </label>
        <div class="reportes-actions">
            <button type="submit">Aplicar filtros</button>
            <a href="{{ route('admin.reportes.solicitudes') }}">Limpiar</a>
            <x-reportes-descargar-pdf :href="route('admin.reportes.inscripciones.pdf', $filtros)" :preview-href="route('admin.reportes.visor', ['tipo' => 'inscripciones'] + $filtros)" />
        </div>
    </form>
    <p class="text-sm text-gray-500 mt-3">PDF con los filtros aplicados: estado {{ $filtros['estado'] ?? 'todos' }}; búsqueda {{ trim($filtros['q'] ?? '') ?: 'sin restricción' }}. Aplica los cambios de los campos para actualizar la exportación.</p>
</x-admin-reportes-layout>
