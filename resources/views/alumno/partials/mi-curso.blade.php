@php
    $registro = $miCurso['inscripcion'];
    $cursoActual = $registro?->curso;
    $estadoCursoAlumno = $miCurso['vigente'] ? 'Inscripción aprobada' : match ($registro?->estado) {
        'pendiente' => 'Solicitud pendiente',
        'inactiva' => 'Inscripción inactiva',
        'rechazada' => 'Solicitud rechazada',
        'aprobada' => 'Sin inscripción vigente',
        null => 'Sin inscripción vigente',
        default => 'Estado: '.$registro->estado,
    };
@endphp
<section class="mi-curso" aria-labelledby="mi-curso-titulo">
    <div class="mi-curso-heading">
        <h3 id="mi-curso-titulo">Mi curso</h3>
        <span class="mi-curso-estado {{ $miCurso['vigente'] ? 'mi-curso-aprobada' : '' }}">{{ $estadoCursoAlumno }}</span>
    </div>
    @foreach($miCurso['avisos'] as $aviso)
        <p class="mi-curso-aviso" role="status">{{ $aviso }}</p>
    @endforeach
    @if($registro)
        <p class="mi-curso-nombre">{{ $cursoActual?->nombre ?: 'Curso no disponible' }}</p>
        @if(!$miCurso['vigente'])
            <p class="mi-curso-ayuda">{{ $registro->estado === 'pendiente' ? 'Tu solicitud está en revisión. Aún no tienes una inscripción vigente para este curso.' : 'Este registro no representa una inscripción vigente. No se muestran materias como asignadas actualmente.' }}</p>
        @endif
        <dl class="mi-curso-datos">
            <div><dt>Sección</dt><dd>{{ $cursoActual?->seccion ?: 'No disponible' }}</dd></div>
            <div><dt>Turno</dt><dd>{{ $cursoActual?->nivel ?: 'No disponible' }}</dd></div>
            <div><dt>Horario de clases</dt><dd>@foreach($miCurso['horarios'] as $linea)<div>{{ $linea }}</div>@endforeach</dd></div>
            <div><dt>Fecha de inicio</dt><dd>{{ $cursoActual?->fecha_inicio?->format('d/m/Y') ?? 'No disponible' }}</dd></div>
        </dl>
        @if($miCurso['vigente'])
            <h4 class="mi-curso-materias-titulo">Mis materias</h4>
            <ul class="mi-curso-materias">
                @forelse($miCurso['materias'] as $materia)
                    <li><span>{{ $materia['nombre'] }}</span><small>Docente: {{ $materia['docente'] ?: 'No disponible' }}</small></li>
                @empty
                    <li>No hay materias disponibles para mostrar en este curso.</li>
                @endforelse
            </ul>
        @endif
    @else
        <p class="mi-curso-ayuda">No tienes una inscripción vigente que pueda mostrarse. Consulta el apartado Inscripción o contacta a Administración.</p>
    @endif
</section>
