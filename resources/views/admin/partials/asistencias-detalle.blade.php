<p class="detalle-mensaje">{{ number_format($asistencias->total()) }} registros filtrados · Mostrando {{ $asistencias->firstItem() ?? 0 }}–{{ $asistencias->lastItem() ?? 0 }}</p>
<div class="tabla-scroll" role="region" aria-label="Asistencias individuales" tabindex="0">
    <table class="reporte-tabla">
        <caption class="solo-lectores">Asistencias individuales, de la más reciente a la más antigua</caption>
        <thead><tr>
            <th scope="col">Curso</th><th scope="col">Materia / Programa</th><th scope="col">Fecha</th>
            <th scope="col">Estado</th><th scope="col">Docente</th>
        </tr></thead>
        <tbody>
            @forelse($asistencias as $asistencia)
                <tr data-asistencia-id="{{ $asistencia->id }}">
                    <td>{{ $asistencia->curso->nombre ?? '—' }}</td>
                    <td><p class="materia-nombre">{{ $asistencia->detalleCurso->materia->nombre ?? '—' }}</p><p class="dato-secundario">{{ $asistencia->detalleCurso->materia->codigo ?? '' }}</p></td>
                    <td class="fecha-registro"><time datetime="{{ $asistencia->fecha->format('Y-m-d') }}">{{ $asistencia->fecha->format('d/m/Y') }}</time></td>
                    <td>
                        @switch($asistencia->estado)
                            @case('presente') <span class="estado-etiqueta estado-presente">Presente</span> @break
                            @case('ausente') <span class="estado-etiqueta estado-ausente">Ausente</span> @break
                            @case('permiso') <span class="estado-etiqueta estado-permiso">Permiso</span> @break
                            @default <span class="estado-etiqueta estado-otro">{{ ucfirst($asistencia->estado ?? 'Sin estado') }}</span>
                        @endswitch
                    </td>
                    <td>{{ $asistencia->detalleCurso->maestro->nombre_completo ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No hay registros en esta página.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($asistencias->hasPages())
    <div class="detalle-paginacion">{{ $asistencias->links() }}</div>
@endif
