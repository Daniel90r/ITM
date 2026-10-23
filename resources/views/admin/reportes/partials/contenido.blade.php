                @if($errors->any())
                    <div role="alert" class="mb-4 p-4 rounded-lg bg-red-50 text-red-700">
                        <p>Revisa los filtros:</p>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @if($tipo === 'asistencias')
                    <p class="text-sm mb-3">Al cambiar de curso, aplica los filtros para actualizar las materias disponibles.</p>
                @endif
                <form method="GET" action="{{ route('admin.reportes.show', $tipo) }}" class="reportes-filters">
                    <label>Curso
                        <select name="curso_id" @if($tipo === 'asistencias') onchange="this.form.elements.detalle_curso_id.value = ''" @endif>
                            <option value="">Todos los cursos</option>
                            @foreach($cursos as $curso)
                                <option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }} · {{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($estados)
                        <label>{{ $tipo === 'alumnos' ? 'Estado del alumno' : 'Estado' }}
                            <select name="estado"><option value="">Todos los estados</option>
                                @foreach($estados as $valor => $etiqueta)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                    @if($tipo === 'calificaciones')
                        <label>Materia<select name="materia_id"><option value="">Todas las materias activas</option>
                            @foreach($materias as $materia)<option value="{{ $materia->id }}" @selected(($filtros['materia_id'] ?? '') == $materia->id)>{{ $materia->nombre }}</option>@endforeach
                        </select></label>
                    @endif
                    @if($tipo === 'asistencias')
                        <label>Materia del curso<select name="detalle_curso_id">
                            <option value="">Todas las materias</option>
                            @foreach($detalleCursos as $detalle)
                                <option value="{{ $detalle->id }}" @selected(($filtros['detalle_curso_id'] ?? '') == $detalle->id)>{{ $detalle->materia?->nombre ?? 'Materia no disponible' }} · Asignación {{ $detalle->id }}</option>
                            @endforeach
                        </select></label>
                        <label>Desde<input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}"></label>
                        <label>Hasta<input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}"></label>
                    @endif
                    @if(in_array($tipo, ['mensualidades', 'alumnos']))
                        <label>Año<input type="number" name="anio" required min="1900" max="2100" value="{{ $filtros['anio'] ?? '' }}"></label>
                        <label>Mes<select name="mes"><option value="">Todos los meses</option>
                            @foreach(\App\Services\ReportesAdministrativos::MESES as $numero => $mes)
                                <option value="{{ $numero }}" @selected(($filtros['mes'] ?? '') == $numero)>{{ $mes }}</option>
                            @endforeach
                        </select></label>
                    @endif
                    <div class="reportes-actions">
                        <button type="submit">Aplicar filtros</button>
                        <a href="{{ route('admin.reportes.show', $tipo) }}" class="text-sm underline p-2">Limpiar</a>
                        @if(in_array($tipo, ['alumnos', 'cursos', 'maestros', 'mensualidades']))
                            <x-reportes-descargar-pdf :href="route('admin.reportes.'.$tipo.'.pdf', $filtros)" :preview-href="route('admin.reportes.visor', ['tipo' => $tipo] + $filtros)" />
                        @endif
                    </div>
                </form>
                @if($tipo === 'asistencias')
                    <aside class="reportes-card mt-3" aria-label="Alcance del PDF institucional">
                        <p>El listado conserva los registros históricos capturados. El PDF institucional muestra el control completo de la materia para alumnos con inscripción aprobada actual, dentro del período seleccionado; puede incluir alumnos sin registros y excluir registros históricos de alumnos que ya no están inscritos.</p>
                        @if(!empty($filtros['estado']))
                            <p class="mt-2">El filtro de estado solo corresponde al listado. Para descargar el control completo, limpia el estado y aplica los filtros; así se conservan presentes, ausentes y permisos en el PDF.</p>
                        @elseif(!empty($filtros['curso_id']) && !empty($filtros['detalle_curso_id']))
                            <p class="mt-2"><a class="underline" href="{{ route('admin.asistencias.pdf', $filtros) }}">Descargar PDF</a></p>
                        @else
                            <p class="mt-2">Selecciona curso y materia y aplica los filtros para descargar el PDF institucional.</p>
                        @endif
                    </aside>
                @endif
                <dl class="reportes-summary">
                    @foreach($reporte['resumen'] as $etiqueta => $valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach
                </dl>
                <p class="text-sm text-gray-600">{{ $reporte['descripcion'] }}</p>
                <div class="reportes-table" role="region" aria-label="Resultados de {{ $tipos[$tipo] }}" tabindex="0">
                    <table>
                        <caption class="sr-only">{{ $tipos[$tipo] }}: resultados de los filtros seleccionados</caption>
                        <thead><tr>@foreach($reporte['columnas'] as $columna)<th scope="col">{{ $columna }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($reporte['filas'] as $fila)
                                <tr>@foreach($fila as $valor)<td>{{ $valor === null || $valor === '' ? '—' : $valor }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($reporte['columnas']) }}" class="text-center text-gray-500">@if($tipo === 'alumnos')No hay alumnos inscritos para el año {{ $filtros['anio'] }} y los filtros seleccionados.@elseif($tipo === 'mensualidades')No hay mensualidades para el año {{ $filtros['anio'] }}@if(isset($filtros['mes'])) y el mes de {{ \App\Services\ReportesAdministrativos::MESES[(int)$filtros['mes']] }}@endif con los filtros seleccionados.@else No hay registros para los filtros seleccionados.@endif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mb-3">{{ $reporte['filas']->total() }} resultados · 25 por página. Los indicadores abarcan todos los resultados filtrados.</p>
                {{ $reporte['filas']->links() }}
