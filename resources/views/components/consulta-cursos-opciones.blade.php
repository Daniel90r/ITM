@props(['cursos', 'filtros' => []])
<option value="">{{ empty($filtros['incluir_historicos']) ? 'Todos los cursos activos' : 'Todos: activos e históricos' }}</option>
@forelse($cursos as $curso)
<option value="{{ $curso->id }}" data-estado-curso="{{ $curso->estado_curso }}" @selected((string)($filtros['curso_id'] ?? '') === (string)$curso->id)>{{ $curso->nombre }} · {{ $curso->nivel }} · {{ $curso->seccion }} · {{ $curso->anio_lectivo }} · {{ $curso->estado_curso }}</option>
@empty
<option value="" disabled>No hay cursos disponibles para este alcance</option>
@endforelse
