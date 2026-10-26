<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden">

    @include('components.admin-sidebar', ['active' => 'cursos'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
                <x-boton-regreso :href="route('admin.cursos')">Volver a Cursos</x-boton-regreso>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">{{ $curso->nombre }} — Detalle</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $curso->nivel }} · {{ $curso->anio_lectivo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:20px;">

             {{-- Stats del curso --}}
            @php
                $inscritos = $curso->inscripciones()->aprobada()->count();
                $inscritosAprobados = $curso->inscripciones()->aprobada()->get();
                $porcentaje = min(round(($inscritos / 20) * 100), 100);
            @endphp
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px;">
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px;">
                    <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Alumnos inscritos</p>
                    <p style="font-size:26px; font-weight:700; color:#111827; margin:0;">{{ $inscritos }} / 20</p>
                    @if($curso->esta_lleno)
                        <p style="font-size:12px; color:#dc2626; margin:4px 0 0;">Cupo lleno</p>
                    @else
                        <p style="font-size:12px; color:#16a34a; margin:4px 0 0;">{{ $curso->cupos_disponibles }} disponible(s)</p>
                    @endif
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px;">
                    <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Materias</p>
                    <p style="font-size:26px; font-weight:700; color:#111827; margin:0;">{{ $detalleCursosVisibles->count() }}</p>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px;">
                    <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Curso</p>
                    <p style="font-size:26px; font-weight:700; color:#111827; margin:0;">{{ $curso->seccion }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.cursos.orden', $curso) }}" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:20px 24px; display:flex; flex-direction:column; gap:12px;">
    @csrf
    <div style="display:flex; align-items:center; gap:8px;">
        <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
        <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Orden académico de materias</p>
    </div>
    @foreach($detalleCursosVisibles as $detalle)
        <label style="display:flex; align-items:center; gap:12px; font-size:13px;">
            <span style="flex:1;">{{ $detalle->materia->nombre }}</span>
            <input type="number" min="1" name="orden[{{ $detalle->id }}]" value="{{ $detalle->orden_academico }}" style="width:80px; border:1px solid #d1d5db; border-radius:6px; padding:6px;">
        </label>
    @endforeach
    <button type="submit" style="align-self:flex-start; background:#111827; color:#fff; border:none; border-radius:8px; padding:8px 14px; font-size:13px;">Guardar orden</button>
</form>

<div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:20px 24px; display:flex; flex-direction:column; gap:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Información del período</p>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <strong style="font-size:11px; color:#9ca3af; display:block; margin-bottom:4px;">Fecha de inicio</strong>
                        <span style="font-size:13px; color:#111827;">{{ $curso->fecha_inicio?->translatedFormat('d \d\e F \d\e Y') ?? 'Sin definir' }}</span>
                    </div>
                    <div>
                        <strong style="font-size:11px; color:#9ca3af; display:block; margin-bottom:4px;">Fecha de finalización</strong>
                        <span style="font-size:13px; color:#111827;">{{ $curso->fecha_fin?->translatedFormat('d \d\e F \d\e Y') ?? 'Sin definir' }}</span>
                    </div>
                </div>
                <div>
                    <div style="margin-bottom:12px;">
                        <strong style="font-size:12px; color:#475569;">Horario semanal</strong>
                        @foreach(app(\App\Services\HorariosCurso::class)->lineas($curso) as $linea)
                            <p style="font-size:13px; color:#334155; margin:4px 0;">{{ $linea }}</p>
                        @endforeach
                    </div>
                    <strong style="font-size:11px; color:#9ca3af; display:block; margin-bottom:4px;">Estado</strong>
                    @switch($curso->estado_curso)
                        @case('PROXIMO')
                            <span style="background:#eff6ff; color:#1d4ed8; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">Próximo</span>
                            @break
                        @case('ACTIVO')
                            <span style="background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">Activo</span>
                            @break
                        @case('FINALIZADO')
                            <span style="background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">Finalizado</span>
                            @break
                        @default
                            <span style="background:#f3f4f6; color:#6b7280; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">Sin fechas</span>
                    @endswitch
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                {{-- Alumnos inscritos --}}
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                    <div style="padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                        <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                        <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Alumnos inscritos</p>
                    </div>
                    <div style="max-height:300px; overflow-y:auto;">
                        @forelse($curso->inscripciones as $inscripcion)
                        @php
                            // Datos precargados por SeccionController@show (sin consultas en la vista).
                            // Son los NOMBRES de las materias opcionales que este alumno lleva en el curso.
                            $materiasOpcionalesDelAlumno = $materiasOpcionalesPorAlumno->get($inscripcion->alumno_id, collect());
                        @endphp
                        <div style="display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid #f3f4f6;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                {{ strtoupper(substr($inscripcion->alumno->nombre,0,1).substr($inscripcion->alumno->apellido,0,1)) }}
                            </div>
                            <div>
                                <p style="font-size:13px; font-weight:500; color:#111827; margin:0;">
                                    {{ $inscripcion->alumno->nombre }} {{ $inscripcion->alumno->apellido }}
                                    @foreach($materiasOpcionalesDelAlumno as $materiaOpcional)
                                        <span style="display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#1d4ed8; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600; margin-left:8px; white-space:nowrap;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-2.21 1.79-4 4-4s4 1.79 4 4v.18z"/></svg>
                                            {{ $materiaOpcional }}
                                        </span>
                                    @endforeach
                                </p>
                                <p style="font-size:11px; color:#9ca3af; margin:0;">{{ $inscripcion->alumno->codigo }}</p>
                            </div>
                        </div>
                        @empty
                        <p style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">Sin alumnos inscritos</p>
                        @endforelse
                    </div>
                </div>

                {{-- Materias y maestros --}}
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                    <div style="padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                        <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                        <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Materias y Maestros</p>
                    </div>
                    <div style="max-height:300px; overflow-y:auto;">
                        @forelse($detalleCursosConOpcional as $detalle)
                        <div style="padding:12px 20px; border-bottom:1px solid #f3f4f6;">
                            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <div style="flex:1; min-width:200px;">
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                                        <p style="font-size:13px; font-weight:600; color:#111827; margin:0;">{{ $detalle->materia->nombre }}</p>
                                        @unless($detalle->materia->activa)
                                            <span style="background:#f3f4f6; color:#6b7280; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600; white-space:nowrap;" title="Materia inactiva en el catálogo, pero conserva notas o asistencias en este curso.">Inactiva · histórico</span>
                                        @endunless
                                    </div>
                                    @if($detalle->materia->es_opcional)
                                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <span style="background:#fffbeb; color:#92400e; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">Opcional</span>
                                            @if(isset($detalle->alumnos_opcional))
                                                <span style="color:#6b7280; font-size:11px;">{{ $detalle->alumnos_opcional->count() }} de {{ $detalle->total_alumnos_curso }} alumnos</span>
                                            @else
                                                <span style="color:#6b7280; font-size:11px;">0 de {{ $detalle->total_alumnos_curso }} alumnos</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                                    @if($detalle->materia->es_opcional && isset($detalle->alumnos_opcional))
                                        <button type="button"
                                            onclick="toggleAlumnosOpcional(this, {{ $detalle->id }})"
                                            data-alumnos='{{ json_encode($detalle->alumnos_opcional->map(function($a) { return ["id" => $a->id, "nombre" => $a->nombre, "apellido" => $a->apellido, "codigo" => $a->codigo]; })) }}'
                                            style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:11px; font-weight:600; color:#2563eb; cursor:pointer; padding:6px 12px;"
                                            onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                            Ver alumnos
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Lista expandible de alumnos de materia opcional --}}
                            @if($detalle->materia->es_opcional && isset($detalle->alumnos_opcional))
                            <div id="alumnos-opcional-{{ $detalle->id }}" style="display:none; margin-top:10px; padding:10px 0 0 0; border-top:1px solid #f3f4f6;">
                                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:8px;">
                                    @foreach($detalle->alumnos_opcional as $alumno)
                                    <div style="display:flex; align-items:center; gap:8px; padding:8px 12px; background:#f9fafb; border-radius:8px; font-size:12px; color:#374151;">
                                        <div style="width:24px; height:24px; border-radius:50%; background:#1d4ed8; display:flex; align-items:center; justify-content:center; color:#fff; font-size:9px; font-weight:700; flex-shrink:0;">
                                            {{ strtoupper(substr($alumno->nombre,0,1).substr($alumno->apellido,0,1)) }}
                                        </div>
                                        <div>
                                            <p style="font-size:11px; font-weight:500; color:#111827; margin:0;">{{ $alumno->nombre }} {{ $alumno->apellido }}</p>
                                            <p style="font-size:10px; color:#9ca3af; margin:0;">{{ $alumno->codigo }}</p>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <p style="font-size:12px; color:#6b7280; margin:8px 0 0 0;">
                                @if($detalle->maestro)
                                    Prof. {{ $detalle->maestro->nombre }} {{ $detalle->maestro->apellido }}
                                @else
                                    <span style="color:#d97706; font-style:italic;">Sin maestro asignado</span>
                                @endif
                            </p>
                        </div>
                        @empty
                        <p style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">Sin materias asignadas</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</x-app-layout>

@push('scripts')
<script>
function toggleAlumnosOpcional(btn, detalleId) {
    const container = document.getElementById('alumnos-opcional-' + detalleId);
    if (!container) return;

    const isHidden = container.style.display === 'none';
    container.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? 'Ocultar alumnos' : 'Ver alumnos';

    // Toggle icon/chevron if needed
    btn.style.background = isHidden ? '#dbeafe' : '#eff6ff';
}
</script>
@endpush
