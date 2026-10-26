<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">

    <x-docente-sidebar :maestro="$maestro" active="asistencia" />

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#ffffff;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:36px; height:36px; background:#3b82f6; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-0 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Registro de asistencia</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $maestro->nombre_completo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; overflow-y:auto; padding:24px;">

            @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; color:#16a34a; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px;">
                {{ session('success') }}
            </div>
            @endif

            <form method="GET" action="{{ route('docente.asistencia') }}" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:18px 20px; margin-bottom:20px;"><x-consulta-cursos-alcance :filtros="$filtrosCursos" />
                <h3 style="font-size:14px; font-weight:600; margin:0 0 14px; color:#1f2937;">Seleccionar curso y materia</h3>

                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                    <div style="display:flex; flex-direction:column; gap:5px; flex:1 1 280px; min-width:220px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Curso</label>
                        <select name="curso_id"
                                onchange="this.form.submit()"
                                style="background:#ffffff; border:1px solid #d1d5db; border-radius:8px; padding:9px 12px; color:#1f2937; font-size:13px; outline:none; width:100%;"
                                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                            <option value="">-- Seleccionar curso --</option>
                            @foreach($cursos as $opcionCurso)
                            <option value="{{ $opcionCurso->id }}" data-estado-curso="{{ $opcionCurso->estado_curso }}" {{ $cursoId == $opcionCurso->id ? 'selected' : '' }}>
                                {{ $opcionCurso->nombre }} · {{ $opcionCurso->nivel }} · {{ $opcionCurso->seccion }} · {{ $opcionCurso->anio_lectivo }} · {{ $opcionCurso->estado_curso }}
                            </option>
                            @endforeach
                        </select>
                        @if($cursos->isEmpty())<p style="font-size:12px">No hay cursos asignados disponibles para este alcance.</p>@endif
                    </div>

                    @if($curso)
                    <div style="display:flex; flex-direction:column; gap:5px; flex:1 1 240px; min-width:200px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Materia / Programa</label>
                        <select name="detalle_curso_id"
                                onchange="this.form.submit()"
                                style="background:#ffffff; border:1px solid #d1d5db; border-radius:8px; padding:9px 12px; color:#1f2937; font-size:13px; outline:none; width:100%;"
                                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                            <option value="">-- Seleccionar materia --</option>
                            @foreach($materias as $opcionMateria)
                            <option value="{{ $opcionMateria->id }}" {{ $detalleCursoId == $opcionMateria->id ? 'selected' : '' }}>
                                {{ $opcionMateria->materia->nombre }}{{ $opcionMateria->materia->es_opcional ? ' (opcional)' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div style="display:flex; flex-direction:column; gap:5px; flex:1 1 190px; min-width:160px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Fecha</label>
                        <input type="date" name="fecha" value="{{ $fecha }}"
                               style="background:#ffffff; border:1px solid #d1d5db; border-radius:8px; padding:9px 12px; color:#1f2937; font-size:13px; outline:none;"
                               onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                    </div>

                    <button type="submit"
                            style="padding:9px 18px; background:#3b82f6; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer;"
                            onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                        Buscar
                    </button>
                </div>
            </form>

            @if($cursos->isEmpty())
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:52px; text-align:center;">
                <div style="width:54px; height:54px; background:#f3f4f6; border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                </div>
                <p style="font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px;">No tiene materias asignadas actualmente.</p>
                <p style="font-size:13px; color:#6b7280; margin:0;">Contacta con el administrador para que te asigne un programa.</p>
            </div>
            @elseif(!$curso)
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:52px; text-align:center;">
                <div style="width:54px; height:54px; background:#f3f4f6; border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                </div>
                <p style="font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px;">Selecciona un curso para registrar asistencia</p>
                <p style="font-size:13px; color:#6b7280; margin:0;">Solo aparecen los cursos donde tienes alguna materia asignada.</p>
            </div>
            @elseif(!$detalle)
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:52px; text-align:center;">
                <p style="font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px;">Selecciona la materia que vas a impartir</p>
                <p style="font-size:13px; color:#6b7280; margin:0;">Cada materia tiene su propio registro de asistencia.</p>
            </div>
            @elseif($alumnos->isEmpty())
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:52px; text-align:center;">
                <p style="font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px;">No hay alumnos activos inscritos</p>
                <p style="font-size:13px; color:#6b7280; margin:0;">Este curso aún no tiene alumnos inscritos activos.</p>
            </div>
            @else
            @php
                $presentes = 0;
                $ausentes = 0;
                $permisos = 0;
                foreach ($alumnos as $alumno) {
                    $estado = $asistencias->get($alumno->id)?->estado ?? 'presente';
                    if ($estado === 'ausente') $ausentes++;
                    elseif ($estado === 'permiso') $permisos++;
                    else $presentes++;
                }
            @endphp

            <form method="POST" action="{{ route('docente.asistencia.guardar') }}">
                @csrf
                <input type="hidden" name="detalle_curso_id" value="{{ $detalle->id }}">
                <input type="hidden" name="fecha" value="{{ $fecha }}">

                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; margin-bottom:20px;">
                    <div style="padding:14px 20px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; border-bottom:1px solid #e5e7eb;">
                        <div>
                            <p style="font-size:14px; font-weight:600; color:#1f2937; margin:0;">
                                {{ $detalle->materia->nombre }}@if($detalle->materia->es_opcional) <span style="font-size:11px; color:#6b7280; font-weight:500;">(opcional)</span> @endif
                            </p>
                            <p style="font-size:12px; color:#6b7280; margin:6px 0 0;">
                                Curso {{ $curso->seccion ?? '' }} — {{ $curso->nivel }} ({{ $curso->anio_lectivo }}) · {{ $alumnos->count() }} alumnos · {{ $fecha }}
                            </p>
                        </div>

                        <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end;">
                            <span style="display:inline-flex; align-items:center; gap:5px; padding:6px 10px; background:#dcfce7; color:#166534; border-radius:999px; font-size:12px; font-weight:600;">
                                Presentes: {{ $presentes }}
                            </span>
                            <span style="display:inline-flex; align-items:center; gap:5px; padding:6px 10px; background:#fee2e2; color:#991b1b; border-radius:999px; font-size:12px; font-weight:600;">
                                Ausentes: {{ $ausentes }}
                            </span>
                            <span style="display:inline-flex; align-items:center; gap:5px; padding:6px 10px; background:#fef3c7; color:#92400e; border-radius:999px; font-size:12px; font-weight:600;">
                                Con permiso: {{ $permisos }}
                            </span>
                        </div>
                    </div>

                    <div style="padding:12px 20px; display:flex; flex-wrap:wrap; gap:8px; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                        <button type="button" onclick="marcarTodos('presente')"
                                style="padding:8px 12px; background:#10b981; border:none; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;">
                            Marcar todos presentes
                        </button>
                        <button type="button" onclick="marcarTodos('ausente')"
                                style="padding:8px 12px; background:#ef4444; border:none; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;">
                            Marcar todos ausentes
                        </button>
                        <button type="button" onclick="marcarTodos('permiso')"
                                style="padding:8px 12px; background:#f59e0b; border:none; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;">
                            Marcar todos con permiso
                        </button>
                    </div>

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:10px 16px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">#</th>
                                    <th style="padding:10px 12px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Alumno</th>
                                    <th style="padding:10px 8px; text-align:center; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase; width:210px;">Estado</th>
                                    <th style="padding:10px 8px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Observación / motivo de permiso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alumnos as $i => $alumno)
                                @php $asistencia = $asistencias->get($alumno->id); $estado = $asistencia?->estado ?? 'presente'; @endphp
                                <tr style="border-top:1px solid #f3f4f6;" class="asistencia-row" data-estado="{{ $estado }}"
                                    onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <td style="padding:10px 16px; color:#9ca3af;">{{ $i+1 }}</td>
                                    <td style="padding:10px 12px;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <div style="width:30px; height:30px; border-radius:50%; background:#3b82f6; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                                {{ strtoupper(substr($alumno->nombre,0,1)) }}
                                            </div>
                                            <div>
                                                <p style="font-size:13px; font-weight:500; color:#1f2937; margin:0;">{{ $alumno->nombre_completo }}</p>
                                                <p style="font-size:11px; color:#6b7280; margin:0;">{{ $alumno->codigo }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:7px 8px; text-align:center;">
                                        <select name="asistencias[{{ $alumno->id }}][estado]"
                                                data-row="{{ $alumno->id }}"
                                                onchange="actualizarEstado(this)"
                                                style="width:100%; text-align:center; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:6px; font-size:13px; outline:none;"
                                                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                                            <option value="presente" {{ $estado === 'presente' ? 'selected' : '' }}>Presente</option>
                                            <option value="ausente" {{ $estado === 'ausente' ? 'selected' : '' }}>Ausente</option>
                                            <option value="permiso" {{ $estado === 'permiso' ? 'selected' : '' }}>Ausente con permiso</option>
                                        </select>
                                    </td>
                                    <td style="padding:7px 8px;">
                                        <input type="text" name="asistencias[{{ $alumno->id }}][observacion]" value="{{ $asistencia?->observacion ?? '' }}" maxlength="500"
                                               placeholder="Opcional"
                                               style="width:100%; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:7px 9px; font-size:13px; outline:none;"
                                               onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:12px 20px; display:flex; align-items:center; justify-content:flex-end; gap:10px; border-top:1px solid #e5e7eb; background:#f9fafb;">
                        <a href="{{ route('docente.asistencia.reporte', ['detalle_curso_id' => $detalle->id, 'fecha' => $fecha]) }}" target="_blank"
                           style="padding:9px 18px; background:#111827; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none;">
                            📄 Generar reporte
                        </a>
                        <button type="submit"
                                style="padding:9px 18px; background:#3b82f6; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                            Guardar asistencia
                        </button>
                    </div>
                </div>
            </form>
            @endif

        </div>
    </div>
</div>

@push('scripts')
@vite('resources/js/docente/asistencia.js')
@endpush
<x-logout-modal />
</x-app-layout>
