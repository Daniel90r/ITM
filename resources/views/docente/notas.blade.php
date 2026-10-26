@php
function promColor(int $p): string {
    if ($p >= 9) return '#10b981';
    if ($p >= 7) return '#f59e0b';
    if ($p >= 6) return '#f97316';
    return '#ef4444';
}
@endphp

<x-app-layout>
{{-- VISTA: DOCENTE - REGISTRO DE NOTAS --}}

<div style="display:flex; height:100vh; overflow:hidden;">

    {{-- Sidebar como componente separado --}}
    <x-docente-sidebar :maestro="$maestro" active="notas" />

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#ffffff;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:36px; height:36px; background:#3b82f6; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Panel del Docente</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $maestro->nombre_completo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; overflow-y:auto; padding:24px;">

            @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; color:#16a34a; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px;">
                ✓ {{ session('success') }}
            </div>
            @endif

            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:18px 20px; margin-bottom:20px;">
                <h3 style="font-size:14px; font-weight:600; margin:0 0 14px; color:#1f2937;">Seleccionar curso y materia</h3>

<div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:18px 20px; margin-bottom:20px;">
                <h3 style="font-size:14px; font-weight:600; margin:0 0 14px; color:#1f2937;">Seleccionar curso y materia</h3>

                <form method="GET" action="{{ route('docente.notas') }}" id="filtroForm" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;"><x-consulta-cursos-alcance :filtros="$filtrosCursos" />
                    <input type="hidden" name="detalle_curso_id" id="inputDetalleCursoId" value="{{ $detalleCursoId }}">

                    <div style="display:flex; flex-direction:column; gap:5px; flex:1 1 200px; min-width:180px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Curso</label>
                        <select id="selectCurso" name="curso_id"
                                style="background:#ffffff; border:1px solid #d1d5db; border-radius:8px; padding:9px 12px; color:#1f2937; font-size:13px; outline:none; cursor:pointer; width:100%;"
                                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'"
                                onchange="filtrarMaterias()">
                            <option value="">-- Seleccionar curso --</option>
                            @foreach($cursos as $grupo)
                            <option value="{{ $grupo['curso']->id }}" data-estado-curso="{{ $grupo['curso']->estado_curso }}"
                                {{ $cursoSeleccionado == $grupo['curso']->id ? 'selected' : '' }}>
                                {{ $grupo['curso']->nombre }} · {{ $grupo['curso']->nivel }} · {{ $grupo['curso']->seccion }} · {{ $grupo['curso']->anio_lectivo }} · {{ $grupo['curso']->estado_curso }}
                            </option>
                            @endforeach
                        </select>
                        @if($cursos->isEmpty())<p style="font-size:12px">No hay cursos asignados disponibles para este alcance.</p>@endif
                    </div>

                    <div style="display:flex; flex-direction:column; gap:5px; flex:1 1 200px; min-width:180px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Materia</label>
                        <select id="selectMateria"
                                style="background:#ffffff; border:1px solid #d1d5db; border-radius:8px; padding:9px 12px; color:#1f2937; font-size:13px; outline:none; cursor:pointer; width:100%;"
                                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                            <option value="">-- Primero selecciona un curso --</option>
                            @foreach($detalleCursos as $dc)
                            <option value="{{ $dc->id }}"
                                    data-curso="{{ $dc->curso_id }}"
                                    {{ $detalleCursoId == $dc->id ? 'selected' : '' }}
                                    style="display:none;">
                                {{ $dc->materia->nombre }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" onclick="submitFiltro()"
                            style="padding:9px 18px; background:#3b82f6; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;"
                            onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                        Buscar
                    </button>
                </form>
            </div>
            </div>

            @if($detalle && $alumnos->count() > 0)
            <form method="POST" action="{{ route('docente.notas.guardar') }}">
                @csrf
                <input type="hidden" name="detalle_curso_id" value="{{ $detalleCursoId }}">

                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                    <div style="padding:14px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e5e7eb;">
                        <div>
                            <p style="font-size:14px; font-weight:600; color:#1f2937; margin:0;">
                                Curso {{ $detalle->curso->seccion }}
                                <span style="color:#9ca3af; font-weight:400; margin:0 4px;">·</span>
                                <span style="color:#3b82f6;">{{ $detalle->materia->nombre }}</span>
                            </p>
                            <p style="font-size:12px; color:#6b7280; margin:4px 0 0;">{{ $alumnos->count() }} alumnos · Turno {{ $detalle->curso->nivel }}</p>
                        </div>
                        <button type="submit"
                                style="display:flex; align-items:center; gap:6px; padding:9px 16px; background:#3b82f6; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                            Guardar notas
                        </button>
                    </div>

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:10px 16px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">#</th>
                                    <th style="padding:10px 12px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Alumno</th>
                                    <th style="padding:10px 8px; text-align:center; color:#8b5cf6; font-weight:600; font-size:11px; text-transform:uppercase; width:100px;">Laboratorio</th>
                                    <th style="padding:10px 8px; text-align:center; color:#f59e0b; font-weight:600; font-size:11px; text-transform:uppercase; width:100px;">Ex. Teórico</th>
                                    <th style="padding:10px 8px; text-align:center; color:#10b981; font-weight:600; font-size:11px; text-transform:uppercase; width:100px;">Práctica</th>
                                    <th style="padding:10px 8px; text-align:center; color:#1f2937; font-weight:700; font-size:11px; text-transform:uppercase; width:70px;">Promedio</th>
                                    <th style="padding:10px 8px; text-align:center; color:#ef4444; font-weight:600; font-size:11px; text-transform:uppercase; width:100px;">Conducta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alumnos as $i => $alumno)
                                @php $nota = $notas->get($alumno->id); @endphp
                                <tr style="border-top:1px solid #f3f4f6;" class="nota-row" data-alumno="{{ $alumno->id }}"
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
                                        <input type="number" name="notas[{{ $alumno->id }}][laboratorio]" value="{{ $nota?->laboratorio ?? '' }}"
                                               min="0" max="10" step="0.1" placeholder="—" class="nota-input"
                                               style="width:68px; text-align:center; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:6px; color:#8b5cf6; font-size:13px; outline:none;"
                                               onfocus="this.style.borderColor='#8b5cf6'" onblur="this.style.borderColor='#d1d5db'"
                                               oninput="calcProm(this.closest('tr'))">
                                    </td>
                                    <td style="padding:7px 8px; text-align:center;">
                                        <input type="number" name="notas[{{ $alumno->id }}][examen_teorico]" value="{{ $nota?->examen_teorico ?? '' }}"
                                               min="0" max="10" step="0.1" placeholder="—" class="nota-input"
                                               style="width:68px; text-align:center; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:6px; color:#f59e0b; font-size:13px; outline:none;"
                                               onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#d1d5db'"
                                               oninput="calcProm(this.closest('tr'))">
                                    </td>
                                    <td style="padding:7px 8px; text-align:center;">
                                        <input type="number" name="notas[{{ $alumno->id }}][practica]" value="{{ $nota?->practica ?? '' }}"
                                               min="0" max="10" step="0.1" placeholder="—" class="nota-input"
                                               style="width:68px; text-align:center; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:6px; color:#10b981; font-size:13px; outline:none;"
                                               onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#d1d5db'"
                                               oninput="calcProm(this.closest('tr'))">
                                    </td>
                                    <td style="padding:7px 8px; text-align:center;">
                                        <span id="prom-{{ $alumno->id }}"
                                              style="display:inline-flex; width:34px; height:34px; border-radius:50%; align-items:center; justify-content:center; font-size:13px; font-weight:700;
                                              {{ $nota?->promedio !== null ? 'background:'.promColor($nota->promedio).'; color:#fff;' : 'background:#f3f4f6; color:#9ca3af;' }}">
                                            {{ $nota?->promedio ?? '—' }}
                                        </span>
                                    </td>
                                    <td style="padding:7px 8px; text-align:center;">
                                        @include('docente.partials.conducta-select', ['alumno' => $alumno, 'nota' => $nota])
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:12px 20px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid #e5e7eb; background:#f9fafb;">
                        <p style="font-size:12px; color:#6b7280; margin:0;">Promedio = promedio de notas ingresadas · redondeado a entero</p>
                        <button type="submit"
                                style="display:flex; align-items:center; gap:6px; padding:9px 16px; background:#3b82f6; border:none; border-radius:8px; color:#fff; font-size:13px; font-weight:600; cursor:pointer;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                            Guardar
                        </button>
                    </div>
                </div>
            </form>

            @elseif(!$detalleCursoId)
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:52px; text-align:center;">
                <div style="width:54px; height:54px; background:#f3f4f6; border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <p style="font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px;">Selecciona un curso y materia para comenzar</p>
                <p style="font-size:13px; color:#6b7280; margin:0;">Elige primero el curso y luego la materia.</p>
            </div>
            @else
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:40px; text-align:center;">
                <p style="color:#6b7280; font-size:13px;">No hay alumnos inscritos en este curso.</p>
            </div>
            @endif

        </div>
    </div>
</div>

@push('scripts')
@vite('resources/js/docente/notas.js')
@endpush
<x-logout-modal />
</x-app-layout>
