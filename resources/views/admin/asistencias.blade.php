<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'asistencias'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Reportes de Asistencia</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Histórico de registros</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; overflow-y:auto; padding:24px;">

            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:16px 20px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                <form method="GET" action="{{ route('admin.asistencias') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; flex:1;">
                    <div style="min-width:180px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Curso</label>
                        <select name="curso_id" onchange="this.form.submit()"
                                style="width:100%; padding:8px 12px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none; cursor:pointer;">
                            <option value="">Todos los cursos</option>
                            @foreach($cursos as $curso)
                            <option value="{{ $curso->id }}" {{ $cursoId == $curso->id ? 'selected' : '' }}>
                                {{ $curso->nombre }} — {{ $curso->nivel }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @if($detalleCursos->count())
                    <div style="min-width:180px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Materia / Programa</label>
                        <select name="detalle_curso_id" onchange="this.form.submit()"
                                style="width:100%; padding:8px 12px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none; cursor:pointer;">
                            <option value="">Todas las materias</option>
                            @foreach($detalleCursos as $dc)
                            <option value="{{ $dc->id }}" {{ $detalleCursoId == $dc->id ? 'selected' : '' }}>
                                {{ $dc->materia->nombre }} ({{ $dc->materia->codigo }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div style="min-width:180px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Docente</label>
                        <select name="maestro_id" onchange="this.form.submit()"
                                style="width:100%; padding:8px 12px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none; cursor:pointer;">
                            <option value="">Todos los docentes</option>
                            @foreach($maestros as $maestro)
                            <option value="{{ $maestro->id }}" {{ $maestroId == $maestro->id ? 'selected' : '' }}>
                                {{ $maestro->nombre_completo }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:140px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Desde</label>
                        <input type="date" name="fecha_desde" value="{{ $fechaDesde }}" onchange="this.form.submit()"
                               style="width:100%; padding:8px 12px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none;">
                    </div>
                    <div style="min-width:140px;">
                        <label style="font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.06em;">Hasta</label>
                        <input type="date" name="fecha_hasta" value="{{ $fechaHasta }}" onchange="this.form.submit()"
                               style="width:100%; padding:8px 12px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none;">
                    </div>
                    <a href="{{ route('admin.asistencias') }}" style="padding:8px 12px; background:none; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; color:#6b7280; text-decoration:none;">
                        Limpiar
                    </a>
                </form>
            </div>

            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb;">
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Listado de asistencias</p>
                </div>

                @if($asistencias->isEmpty())
                <p style="padding:40px; text-align:center; color:#9ca3af; font-size:13px;">No hay registros de asistencia con los filtros seleccionados.</p>
                @else
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:11px 12px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Fecha</th>
                            <th style="padding:11px 12px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Alumno</th>
                            <th style="padding:11px 12px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Curso</th>
                            <th style="padding:11px 12px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Materia</th>
                            <th style="padding:11px 12px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Estado</th>
                            <th style="padding:11px 12px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Docente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($asistencias as $asistencia)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:11px 12px; font-size:13px; color:#6b7280;">{{ \Carbon\Carbon::parse($asistencia->fecha)->format('d/m/Y') }}</td>
                            <td style="padding:11px 12px; font-size:13px; color:#111827;">{{ $asistencia->alumno->nombre_completo }}</td>
                            <td style="padding:11px 12px; font-size:13px; color:#6b7280;">{{ $asistencia->curso->nombre ?? '—' }}</td>
                            <td style="padding:11px 12px; font-size:13px; color:#6b7280;">{{ $asistencia->detalleCurso->materia->nombre ?? '—' }}</td>
                            <td style="padding:11px 12px; text-align:center;">
                                @if($asistencia->estado === 'ausente')
                                    <span style="background:#fef2f2; color:#dc2626; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:600;">Ausente</span>
                                @elseif($asistencia->estado === 'permiso')
                                    <span style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:600;">Permiso</span>
                                @else
                                    <span style="background:#f0fdf4; color:#16a34a; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:600;">Presente</span>
                                @endif
                            </td>
                            <td style="padding:11px 12px; font-size:13px; color:#6b7280;">{{ $asistencia->detalleCurso->maestro->nombre_completo ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="padding:16px 24px; border-top:1px solid #e5e7eb;">
                    {{ $asistencias->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-app-layout>