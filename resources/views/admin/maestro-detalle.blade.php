<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">

    @include('components.admin-sidebar', ['active' => 'maestros'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        {{-- Header --}}
        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de programas del maestro</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $maestro->nombre_completo }} — {{ $maestro->codigo }}</p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                @if($maestro->activo)
                    <span style="font-size:12px; color:#16a34a; background:#f0fdf4; padding:6px 12px; border-radius:8px;">● Activo</span>
                @else
                    <span style="font-size:12px; color:#dc2626; background:#fef2f2; padding:6px 12px; border-radius:8px;">● Inactivo</span>
                @endif
                <a href="{{ route('admin.maestros') }}"
                    style="font-size:12px; color:#6b7280; text-decoration:none; padding:6px 12px; border-radius:8px; background:#f3f4f6;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    ← Regresar
                </a>
            </div>
        </header>

        {{-- Mensajes --}}
        @if(session('success'))
            <div style="background:#f0fdf4; border-bottom:1px solid #dcfce7; padding:12px 24px; flex-shrink:0;">
                <p style="font-size:13px; color:#16a34a; margin:0;">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div style="background:#fef2f2; border-bottom:1px solid #fecaca; padding:12px 24px; flex-shrink:0;">
                <p style="font-size:13px; color:#dc2626; margin:0;">{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div style="background:#fef2f2; border-bottom:1px solid #fecaca; padding:12px 24px; flex-shrink:0;">
                <p style="font-size:13px; color:#dc2626; margin:0;">{{ $errors->count() }} error(es) de validación. Revise los campos marcados.</p>
            </div>
        @endif

        {{-- Contenido --}}
        <div style="flex:1; overflow-y:auto; padding:24px;">

            {{-- Datos básicos y acciones --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; margin-bottom:24px; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="flex:1;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; max-width:600px;">
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">Nombre</p>
                                <p style="font-size:14px; color:#111827; margin:0;">{{ $maestro->nombre_completo }}</p>
                            </div>
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">Código</p>
                                <p style="font-size:14px; font-family:monospace; color:#111827; margin:0;">{{ $maestro->codigo }}</p>
                            </div>
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">Correo</p>
                                <p style="font-size:14px; color:#111827; margin:0;">{{ $maestro->user?->email ?? '—' }}</p>
                            </div>
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">Teléfono</p>
                                <p style="font-size:14px; color:#111827; margin:0;">{{ $maestro->telefono }}</p>
                            </div>
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">DUI</p>
                                <p style="font-size:14px; color:#111827; margin:0;">{{ $maestro->dui }}</p>
                            </div>
                            <div>
                                <p style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px;">Estado</p>
                                <p style="font-size:14px; color:#111827; margin:0;">{{ $maestro->activo ? 'Activo' : 'Inactivo' }}</p>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-shrink:0;">
                        @if($maestro->activo)
                        <button type="button" onclick="abrirModalAsignarCurso()"
                            style="background:#111827; border:none; padding:8px 18px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;"
                            onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                            Asignar programa
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Programas asignados --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                    <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Programas asignados</p>
                </div>

                @if($maestro->detalleCursos->isNotEmpty())
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Curso</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Nivel / Sección</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Programa / Materia</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Código</th>
                            <th style="padding:11px 24px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($maestro->detalleCursos as $detalle)
                        @php
                            $asignacionesActivas = $detalle->asignacionesDocentes->where('estado', 'activa');
                            $asignacionesFinalizadas = $detalle->asignacionesDocentes->where('estado', 'finalizada');
                            $asignacionesTotal = $detalle->asignacionesDocentes->count();
                            $tieneNotas = $detalle->notas_count > 0;

                            $puedeDesasignar = !$tieneNotas && $asignacionesFinalizadas->isEmpty() && $asignacionesTotal <= 1;
                            $esCoherente = $asignacionesActivas->count() === 1 && $asignacionesActivas->first()->maestro_id == $maestro->id;
                            $puedeCambiarMaestro = $esCoherente && $maestrosDisponibles->isNotEmpty();
                            $historialInconsistente = !$esCoherente;
                        @endphp
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:13px 24px; color:#111827; font-size:13px;">{{ $detalle->curso?->nombre ?? '—' }}</td>
                            <td style="padding:0 24px; color:#6b7280; font-size:13px;">
                                {{ $detalle->curso?->nivel ?? '—' }}
                                @if($detalle->curso?->seccion)
                                    / {{ $detalle->curso->seccion }}
                                @endif
                            </td>
                            <td style="padding:0 24px; color:#111827; font-size:13px;">{{ $detalle->materia?->nombre ?? '—' }}</td>
                            <td style="padding:0 24px; color:#6b7280; font-size:13px; font-family:monospace;">{{ $detalle->materia?->codigo ?? '—' }}</td>
                            <td style="padding:13px 24px; text-align:center;">
                                @if($historialInconsistente)
                                    <span style="font-size:11px; color:#dc2626; display:block; margin-bottom:6px;">Historial inconsistente. Revise la asignación.</span>
                                @elseif($esCoherente && !$maestrosDisponibles->isNotEmpty())
                                    <span style="font-size:11px; color:#9ca3af; display:block; margin-bottom:6px;">No hay otro maestro activo disponible.</span>
                                @endif

                                @if($puedeCambiarMaestro)
                                    <button type="button"
                                        onclick="abrirModalCambiarMaestro(@js(route('admin.maestros.asignaciones.cambiar', [$maestro->id, $detalle->id])), @js($detalle->id), @js($detalle->curso?->nombre ?? '—'), @js($detalle->materia?->nombre ?? '—'))"
                                        style="background:#eff6ff; border:none; padding:6px 14px; border-radius:6px; color:#3b82f6; font-size:12px; font-weight:600; cursor:pointer; margin-bottom:6px; display:block; width:100%;">
                                        Cambiar maestro
                                    </button>
                                @endif

                                @if($puedeDesasignar)
                                    <form method="POST" action="{{ route('admin.maestros.desasignar', [$maestro->id, $detalle->id]) }}" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            onclick="return confirm('¿Está seguro de desasignar este programa del maestro?')"
                                            style="background:#fef2f2; border:none; padding:6px 14px; border-radius:6px; color:#dc2626; font-size:12px; font-weight:600; cursor:pointer;">
                                            Desasignar
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size:11px; color:#dc2626; display:block;">No eliminable</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" style="background:#fafbfc; padding:10px 24px;">
                                <details style="margin-top:4px;">
                                    <summary style="cursor:pointer; font-size:12px; color:#374151; font-weight:600;">Ver historial docente</summary>
                                    @if($detalle->asignacionesDocentes->isNotEmpty())
                                    <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:11px;">
                                        <thead>
                                            <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                                <th style="padding:6px 10px; text-align:left; color:#6b7280; font-weight:600;">Maestro</th>
                                                <th style="padding:6px 10px; text-align:left; color:#6b7280; font-weight:600;">Estado</th>
                                                <th style="padding:6px 10px; text-align:left; color:#9ca3af; font-weight:600;">Fecha inicio</th>
                                                <th style="padding:6px 10px; text-align:left; color:#9ca3af; font-weight:600;">Fecha fin</th>
                                                <th style="padding:6px 10px; text-align:left; color:#9ca3af; font-weight:600;">Motivo</th>
                                                <th style="padding:6px 10px; text-align:left; color:#9ca3af; font-weight:600;">Registrado por</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($detalle->asignacionesDocentes as $asignacion)
                                            <tr style="border-top:1px solid #f1f5f9;">
                                                <td style="padding:8px 10px; color:#111827;">{{ $asignacion->maestro?->nombre_completo ?? '—' }}</td>
                                                <td style="padding:8px 10px; color:#6b7280;">
                                                    @if($asignacion->estado === 'activa')
                                                        Activa
                                                    @elseif($asignacion->estado === 'finalizada')
                                                        Finalizada
                                                    @elseif($asignacion->estado === 'programada')
                                                        Programada
                                                    @else
                                                        {{ $asignacion->estado }}
                                                    @endif
                                                </td>
                                                <td style="padding:8px 10px; color:#9ca3af;">
                                                    @if($asignacion->fecha_inicio)
                                                        {{ $asignacion->fecha_inicio->format('d/m/Y') }}
                                                    @else
                                                        No registrada
                                                    @endif
                                                </td>
                                                <td style="padding:8px 10px; color:#9ca3af;">
                                                    @if($asignacion->fecha_fin)
                                                        {{ $asignacion->fecha_fin->format('d/m/Y') }}
                                                    @elseif($asignacion->estado === 'activa')
                                                        Actual
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td style="padding:8px 10px; color:#9ca3af;">{{ $asignacion->motivo_cambio ?? '—' }}</td>
                                                <td style="padding:8px 10px; color:#9ca3af;">
                                                    @if($asignacion->asignado_por)
                                                        {{ $asignacion->asignadoPor?->name ?? '—' }}
                                                    @else
                                                        Migración inicial
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @else
                                    <p style="margin-top:8px; font-size:12px; color:#9ca3af;">No existe historial docente para este programa.</p>
                                    @endif
                                </details>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="padding:40px; text-align:center; color:#9ca3af; font-size:13px;">
                    Este maestro no tiene programas asignados.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('admin.partials.modal-editar-maestro')
@include('admin.partials.modal-asignar-curso')
@include('admin.partials.modal-cambiar-maestro')

@push('scripts')
@vite('resources/js/admin/maestros.js')
@endpush
<x-logout-modal />
</x-app-layout>
