<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">

    @include('components.admin-sidebar', ['active' => 'cursos'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <a href="{{ route('admin.cursos') }}"
                   style="width:32px; height:32px; border-radius:8px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; text-decoration:none; color:#6b7280;"
                   onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
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
                    <p style="font-size:26px; font-weight:700; color:#111827; margin:0;">{{ $curso->detalleCursos->count() }}</p>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px;">
                    <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Curso</p>
                    <p style="font-size:26px; font-weight:700; color:#111827; margin:0;">{{ $curso->seccion }}</p>
                </div>
            </div>

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
                        <div style="display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid #f3f4f6;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                {{ strtoupper(substr($inscripcion->alumno->nombre,0,1).substr($inscripcion->alumno->apellido,0,1)) }}
                            </div>
                            <div>
                                <p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $inscripcion->alumno->nombre }} {{ $inscripcion->alumno->apellido }}</p>
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
                        @forelse($curso->detalleCursos as $detalle)
                        <div style="padding:12px 20px; border-bottom:1px solid #f3f4f6;">
                            <p style="font-size:13px; font-weight:600; color:#111827; margin:0 0 2px;">{{ $detalle->materia->nombre }}</p>
                            <p style="font-size:12px; color:#6b7280; margin:0;">
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
