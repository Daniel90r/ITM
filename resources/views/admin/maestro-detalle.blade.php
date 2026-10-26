<x-app-layout>
@push('styles')
<style>
    #maestro-programas .programas-lista { display:grid; gap:14px; padding:20px; }
    #maestro-programas .programa-tarjeta { min-width:0; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
    #maestro-programas .programa-cabecera { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:20px; }
    #maestro-programas .programa-datos { flex:1; min-width:0; }
    #maestro-programas .programa-nombre { margin:0; font-size:16px; line-height:1.4; font-weight:600; color:#111827; overflow-wrap:anywhere; }
    #maestro-programas .programa-codigo { display:inline-block; margin-top:6px; padding:2px 7px; border:1px solid #e5e7eb; border-radius:5px; background:#f9fafb; color:#6b7280; font-family:monospace; font-size:11px; overflow-wrap:anywhere; }
    #maestro-programas .programa-contexto { display:flex; flex-wrap:wrap; gap:8px 24px; margin:14px 0 0; }
    #maestro-programas .programa-contexto dt { margin-bottom:3px; font-size:10px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:#9ca3af; }
    #maestro-programas .programa-contexto dd { margin:0; font-size:13px; line-height:1.5; color:#374151; overflow-wrap:anywhere; }
    #maestro-programas .programa-acciones { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; max-width:320px; }
    #maestro-programas .programa-acciones form { display:flex; margin:0; }
    #maestro-programas .programa-boton { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:36px; padding:8px 12px; border:1px solid; border-radius:8px; font-family:inherit; font-size:12px; line-height:18px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background .15s; }
    #maestro-programas .programa-boton-cambiar { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    #maestro-programas .programa-boton-cambiar:hover { background:#dbeafe; }
    #maestro-programas .programa-boton-quitar { background:#ffffff; border-color:#fecaca; color:#dc2626; }
    #maestro-programas .programa-boton-quitar:hover { background:#fef2f2; }
    #maestro-programas .programa-boton:focus-visible,
    #maestro-programas .programa-historial summary:focus-visible { outline:2px solid #3b82f6; outline-offset:3px; }
    #maestro-programas .programa-bloqueado { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb; color:#6b7280; font-size:11px; font-weight:500; white-space:nowrap; }
    #maestro-programas .programa-aviso { flex-basis:100%; margin:0; font-size:11px; line-height:1.5; text-align:right; color:#9ca3af; }
    #maestro-programas .programa-aviso-error { color:#dc2626; }
    #maestro-programas .programa-historial { padding:0 20px; border-top:1px solid #f1f5f9; }
    #maestro-programas .programa-historial summary { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 0; cursor:pointer; list-style:none; font-size:12px; color:#374151; font-weight:500; }
    #maestro-programas .programa-historial summary::-webkit-details-marker { display:none; }
    #maestro-programas .historial-indicador { display:inline-flex; align-items:center; gap:8px; color:#9ca3af; font-size:11px; font-weight:400; white-space:nowrap; }
    #maestro-programas .historial-flecha { transition:transform .15s; }
    #maestro-programas .programa-historial[open] .historial-flecha { transform:rotate(180deg); }
    #maestro-programas .historial-contenido { overflow-x:auto; margin-bottom:16px; border:1px solid #e5e7eb; border-radius:8px; }
    @media (max-width:1000px) {
        #maestro-programas .programa-cabecera { flex-direction:column; gap:16px; }
        #maestro-programas .programa-acciones { justify-content:flex-start; max-width:none; }
        #maestro-programas .programa-aviso { text-align:left; }
    }
    @media (max-width:767px) {
        #maestro-programas:not([data-staff-shell]) { flex-direction:column; height:100dvh !important; }
        #maestro-programas:not([data-staff-shell]) > aside { width:100% !important; max-height:150px; border-right:0 !important; border-bottom:1px solid #e5e7eb; }
        #maestro-programas > .maestro-contenido { min-height:0; }
        #maestro-programas .maestro-header { flex-wrap:wrap; gap:12px; }
        #maestro-programas .maestro-resumen > div { flex-wrap:wrap; gap:16px; }
        #maestro-programas .maestro-resumen p { overflow-wrap:anywhere; }
        #maestro-programas .programas-lista { padding:12px; gap:12px; }
        #maestro-programas .programa-cabecera { padding:16px; }
        #maestro-programas .programa-historial { padding:0 16px; }
    }
    @media (max-width:480px) {
        #maestro-programas .programa-acciones { display:grid; grid-template-columns:1fr; width:100%; }
        #maestro-programas .programa-boton { width:100%; }
        #maestro-programas .programa-bloqueado { justify-self:start; }
    }
</style>
@endpush
<div id="maestro-programas" style="display:flex; height:100vh; overflow:hidden;">

    @include('components.admin-sidebar', ['active' => 'maestros'])

    <div class="maestro-contenido" style="flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        {{-- Header --}}
        <header class="maestro-header" style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de programas del maestro</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $maestro->nombre_completo }} — {{ $maestro->codigo }}</p>
                </div>
            </div>
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
                @if($maestro->activo)
                    <span style="font-size:12px; color:#16a34a; background:#f0fdf4; padding:6px 12px; border-radius:8px;">● Activo</span>
                @else
                    <span style="font-size:12px; color:#dc2626; background:#fef2f2; padding:6px 12px; border-radius:8px;">● Inactivo</span>
                @endif
                <x-boton-regreso :href="route('admin.maestros')">Volver a Maestros</x-boton-regreso>
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
            <div class="maestro-resumen" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; margin-bottom:24px; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
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
                <div class="programas-lista">
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
                        <article class="programa-tarjeta" aria-labelledby="programa-nombre-{{ $detalle->id }}">
                            <div class="programa-cabecera">
                                <div class="programa-datos">
                                    <h3 class="programa-nombre" id="programa-nombre-{{ $detalle->id }}">{{ $detalle->materia?->nombre ?? '—' }}</h3>
                                    <span class="programa-codigo">Código: {{ $detalle->materia?->codigo ?? '—' }}</span>
                                    <dl class="programa-contexto">
                                        <div>
                                            <dt>Curso</dt>
                                            <dd>{{ $detalle->curso?->nombre ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt>Nivel / Sección</dt>
                                            <dd>
                                                {{ $detalle->curso?->nivel ?? '—' }}
                                                @if($detalle->curso?->seccion)
                                                    / {{ $detalle->curso->seccion }}
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="programa-acciones">
                                @if($historialInconsistente)
                                    <span class="programa-aviso programa-aviso-error">Historial inconsistente. Revise la asignación.</span>
                                @elseif($esCoherente && !$maestrosDisponibles->isNotEmpty())
                                    <span class="programa-aviso">No hay otro maestro activo disponible.</span>
                                @endif

                                @if($puedeCambiarMaestro)
                                    <button type="button"
                                        onclick="abrirModalCambiarMaestro(@js(route('admin.maestros.asignaciones.cambiar', [$maestro->id, $detalle->id])), @js($detalle->id), @js($detalle->curso?->nombre ?? '—'), @js($detalle->materia?->nombre ?? '—'))"
                                        class="programa-boton programa-boton-cambiar">
                                        Cambiar maestro
                                    </button>
                                @endif

                                @if($puedeDesasignar)
                                    <form method="POST" action="{{ route('admin.maestros.desasignar', [$maestro->id, $detalle->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            onclick="return confirm('¿Está seguro de desasignar este programa del maestro?')"
                                            class="programa-boton programa-boton-quitar">
                                            Desasignar
                                        </button>
                                    </form>
                                @else
                                    <span class="programa-bloqueado" title="No se puede desasignar este programa.">
                                        <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                        No eliminable
                                    </span>
                                @endif
                                </div>
                            </div>
                                <details class="programa-historial">
                                    <summary>
                                        <span>Ver historial docente</span>
                                        <span class="historial-indicador">
                                            {{ $asignacionesTotal }} registro(s)
                                            <svg class="historial-flecha" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                        </span>
                                    </summary>
                                    @if($detalle->asignacionesDocentes->isNotEmpty())
                                    <div class="historial-contenido" role="region" aria-label="Historial docente de {{ $detalle->materia?->nombre ?? '—' }}" tabindex="0">
                                    <table style="width:100%; min-width:640px; border-collapse:collapse; font-size:12px;">
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
                                    </div>
                                    @else
                                    <p style="margin-top:8px; font-size:12px; color:#9ca3af;">No existe historial docente para este programa.</p>
                                    @endif
                                </details>
                        </article>
                        @endforeach
                </div>
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
