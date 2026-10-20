<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'solicitudes', 'solicitudesPendientes' => $conteos['pendientes']])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">
        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Solicitudes</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $conteos['total'] }} solicitudes registradas</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:20px; padding:24px; overflow:hidden;">

            @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; border-radius:10px; padding:12px 16px; font-size:13px; color:#16a34a; display:flex; align-items:center; gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; font-size:13px; color:#dc2626;">
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
                <div role="alert" style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; font-size:13px; color:#dc2626;">
                    @foreach($errors->all() as $error)<p style="margin:0;">{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- ═══ CONTADORES ═══ --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; flex-shrink:0;">
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#fefce8; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $conteos['pendientes'] }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Pendientes</p>
                    </div>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $conteos['aprobadas'] }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Aprobadas</p>
                    </div>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#fef2f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $conteos['rechazadas'] }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Rechazadas</p>
                    </div>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#6b7280; flex-shrink:0;">—</div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $conteos['inactivas'] }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Inactivas</p>
                    </div>
                </div>
            </div>

            {{-- ═══ TABLA PRINCIPAL ═══ --}}
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; flex-shrink:0;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                        <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Solicitudes</p>
                    </div>

                    {{-- Filtros --}}
                    <form method="GET" action="{{ route('admin.solicitudes') }}" id="filtrosSolicitudes" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="estado" id="estadoSolicitud" value="{{ $estadoFiltro }}">
                        <div style="position:relative;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="buscarSolicitud" name="q" maxlength="100" value="{{ $busqueda }}" placeholder="Buscar alumno o código..."
                                style="width:220px; padding:8px 12px 8px 30px; font-size:13px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box;"
                                oninput="filtrarSolicitudes()" onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                        <div style="display:flex; gap:6px;" id="filtrosEstado">
                            @foreach(['todos' => 'Todos', 'pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'inactiva' => 'Inactivas'] as $valor => $etiqueta)
                                <button type="button" data-estado="{{ $valor }}" class="filtro-estado {{ $estadoFiltro === $valor ? 'activo' : '' }}" aria-pressed="{{ $estadoFiltro === $valor ? 'true' : 'false' }}" onclick="setFiltroEstado(@js($valor), this)">{{ $etiqueta }}</button>
                            @endforeach
                            @if($conteos['otros'] > 0)
                                <button type="button" data-estado="otros" class="filtro-estado {{ $estadoFiltro === 'otros' ? 'activo' : '' }}" onclick="setFiltroEstado('otros', this)">Otros ({{ $conteos['otros'] }})</button>
                            @endif
                        </div>
                    </form>
                </div>

                <div style="flex:1; min-height:0; overflow:auto;">
                <table style="width:100%; border-collapse:collapse; min-width:780px;">
                    <thead>
                        <tr style="position:sticky; top:0; z-index:2; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Código</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Alumno</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Curso solicitado</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Fecha de solicitud</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Estado</th>
                            <th style="padding:11px 24px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inscripciones as $ins)
                        <tr class="fila-solicitud"
                            data-estado="{{ $ins->estado }}"
                            data-busqueda="{{ strtolower($ins->alumno->nombre_completo . ' ' . $ins->alumno->codigo) }}"
                            style="border-top:1px solid #f1f5f9; background:{{ $loop->even ? '#f8fafc' : '#ffffff' }};"
                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='{{ $loop->even ? '#f8fafc' : '#ffffff' }}'">

                            <td style="padding:13px 24px; color:#6b7280; font-size:13px; font-family:monospace; font-weight:600;">{{ $ins->alumno->codigo }}</td>
                            <td style="padding:13px 24px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                        {{ strtoupper(substr($ins->alumno->nombre,0,1).substr($ins->alumno->apellido,0,1)) }}
                                    </div>
                                    <span style="font-size:13px; font-weight:500; color:#111827;">{{ $ins->alumno->nombre_completo }}</span>
                                </div>
                            </td>
                            <td style="padding:13px 24px; font-size:13px; color:#111827; font-weight:500;">{{ $ins->curso->nombre }}</td>
                            <td style="padding:13px 24px; font-size:13px; color:#6b7280;">{{ \Carbon\Carbon::parse($ins->created_at)->format('d/m/Y') }}</td>
                            <td style="padding:13px 24px;">
                                @include('admin.partials.estado-solicitud', ['estado' => $ins->estado])
                            </td>
                            <td style="padding:13px 24px; text-align:center;">
                                <div style="display:inline-flex; gap:6px; flex-wrap:nowrap; align-items:center; justify-content:center; white-space:nowrap;">
                                    <button type="button" onclick="abrirDetalle({{ $ins->id }})"
                                        style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:12px; font-weight:600; color:#2563eb; cursor:pointer; padding:5px 12px;"
                                        onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                        Ver detalle
                                    </button>

                                    @if($ins->estado === 'pendiente')
                                    <form method="POST" action="{{ route('admin.solicitudes.aprobar', $ins->id) }}"
                                          data-confirm="¿Aprobar la inscripción de {{ $ins->alumno->nombre_completo }}?"
                                          data-confirm-titulo="Aprobar inscripción" data-confirm-boton="Sí, aprobar" data-confirm-tipo="exito">
                                        @csrf
                                        <button type="submit"
                                            style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; font-size:12px; font-weight:600; color:#16a34a; cursor:pointer; padding:5px 12px;"
                                            onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
                                            ✓ Aprobar
                                        </button>
                                    </form>
                                    <button type="button" onclick="abrirRechazo({{ $ins->id }}, '{{ addslashes($ins->alumno->nombre) }} {{ addslashes($ins->alumno->apellido) }}')"
                                        style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; font-size:12px; font-weight:600; color:#dc2626; cursor:pointer; padding:5px 12px;"
                                        onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                        ✕ Rechazar
                                    </button>

                                    @elseif($ins->estado === 'rechazada')
                                    <form method="POST" action="{{ route('admin.solicitudes.reabrir', $ins->id) }}"
                                          data-confirm="¿Reabrir la solicitud de {{ $ins->alumno->nombre_completo }}? Volverá a estado pendiente."
                                          data-confirm-titulo="Reabrir solicitud" data-confirm-boton="Sí, reabrir" data-confirm-tipo="exito">
                                        @csrf
                                        <button type="submit"
                                            style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:12px; font-weight:600; color:#2563eb; cursor:pointer; padding:5px 12px;"
                                            onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                            ↺ Reabrir
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="padding:40px; text-align:center; color:#9ca3af; font-size:13px;">{{ $conteos['total'] === 0 ? 'No hay solicitudes registradas.' : 'No se encontraron solicitudes con los filtros seleccionados.' }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

<div style="display:flex; align-items:center; justify-content:space-between; padding:14px 24px; border-top:1px solid #e5e7eb; flex-wrap:wrap; gap:10px; flex-shrink:0;">
                    <span style="font-size:12px; color:#6b7280;">
                        Mostrando {{ $inscripciones->firstItem() ?? 0 }}–{{ $inscripciones->lastItem() ?? 0 }} de {{ $inscripciones->total() }}
                    </span>
                    <div style="display:flex; gap:8px; align-items:center;">
                        @if($inscripciones->previousPageUrl())
                        <a href="{{ $inscripciones->previousPageUrl() }}" style="padding:7px 14px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; font-weight:600; color:#374151; text-decoration:none; cursor:pointer;"
                           onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">← Anterior</a>
                        @endif
                        <span style="font-size:12px; color:#6b7280;">Página {{ $inscripciones->currentPage() }} de {{ $inscripciones->lastPage() }}</span>
                        @if($inscripciones->nextPageUrl())
                        <a href="{{ $inscripciones->nextPageUrl() }}" style="padding:7px 14px; background:#111827; border:1px solid #111827; border-radius:8px; font-size:12px; font-weight:600; color:#fff; text-decoration:none; cursor:pointer;"
                           onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">Siguiente →</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ DETALLES OCULTOS (uno por solicitud) ═══ --}}
@foreach($inscripciones as $ins)
@php
    $datosEnc = $ins->observacion ? json_decode($ins->observacion, true) : [];
    $esMenor  = !$ins->alumno->es_mayor_de_edad;
@endphp
<div id="detalle-{{ $ins->id }}" style="display:none;">
    {{-- DATOS DEL ALUMNO --}}
    <div style="margin-bottom:18px;">
        <p style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin:0 0 10px;">Datos del alumno</p>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px;">
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Nombre</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->alumno->nombre_completo }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Código</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0; font-family:monospace;">{{ $ins->alumno->codigo }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Fecha de nacimiento</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->alumno->fecha_nacimiento ? \Carbon\Carbon::parse($ins->alumno->fecha_nacimiento)->format('d/m/Y') : '—' }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Teléfono</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->alumno->telefono ?? '—' }}</p></div>
            @if($ins->alumno->dui)
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">DUI</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->alumno->dui }}</p></div>
            @endif
        </div>
    </div>

    {{-- SOLICITUD --}}
    <div style="margin-bottom:18px;">
        <p style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin:0 0 10px;">Solicitud</p>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px;">
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Curso solicitado</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->curso->nombre }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Sección</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->curso->seccion }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Jornada</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $ins->curso->nivel }}</p></div>
            <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Fecha</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ \Carbon\Carbon::parse($ins->created_at)->isoFormat('D [de] MMMM YYYY') }}</p></div>
        </div>
        <div style="margin-top:10px;">
            @include('admin.partials.estado-solicitud', ['estado' => $ins->estado])
            @if($ins->tomara_ingles)
                <span style="display:inline-flex; align-items:center; gap:6px; background:#eff6ff; color:#1d4ed8; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap; margin-left:6px;">🌐 Solicita Inglés</span>
            @endif
            @if($ins->estado === 'rechazada' && $ins->motivo_rechazo)
            <p style="font-size:12px; color:#374151; margin:10px 0 0;"><strong>Motivo:</strong> {{ $ins->motivo_rechazo }}</p>
            @endif
        </div>
    </div>

    {{-- Recuperación administrativa y su auditoría --}}
    @if($ins->estado === 'inactiva')
        <section style="margin-bottom:18px; padding:14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px;">
            <p style="font-size:13px; font-weight:600; color:#111827; margin:0 0 8px;">Recuperar inscripción #{{ $ins->id }}</p>
            @if(!$reactivacionDisponible)
                <p style="font-size:12px; color:#6b7280; margin:0;">La reactivación administrativa todavía no está habilitada. Contacta al responsable del sistema.</p>
            @elseif($ins->reactivacion)
                <p style="font-size:12px; color:#6b7280; margin:0;">Esta inscripción ya fue reactivada anteriormente. No se permite una segunda reactivación.</p>
            @else
                <p style="font-size:12px; color:#6b7280; margin:0 0 12px;">Recuperarás la inscripción del curso {{ $ins->curso->nombre }}, sección {{ $ins->curso->seccion }}. Se conservarán la fecha original, notas, asistencias y materias. Ninguna otra inscripción será desactivada.</p>
                <form method="POST" action="{{ route('admin.solicitudes.reactivar', $ins->id) }}"
                      data-confirm="¿Confirmas recuperar la inscripción #{{ $ins->id }} del curso {{ $ins->curso->nombre }}?"
                      data-confirm-titulo="Reactivar inscripción" data-confirm-boton="Sí, reactivar" data-confirm-tipo="aviso">
                    @csrf
                    <input type="hidden" name="reactivacion_contexto" value="{{ $ins->id }}">
                    <label style="display:block; font-size:12px; color:#374151; font-weight:600; margin-bottom:6px;">Motivo de reactivación
                        <textarea name="motivo_reactivacion" required minlength="10" maxlength="500" rows="3" placeholder="Explica por qué se recupera esta inscripción (10 a 500 caracteres)."
                                  style="display:block; width:100%; box-sizing:border-box; margin-top:6px; padding:10px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; font-weight:400;">{{ (string) old('reactivacion_contexto') === (string) $ins->id ? old('motivo_reactivacion') : '' }}</textarea>
                    </label>
                    <button type="submit" style="padding:8px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#2563eb; font-size:12px; font-weight:600; cursor:pointer;">Reactivar inscripción</button>
                </form>
            @endif
        </section>
    @endif
    @if($reactivacionDisponible && $ins->reactivacion)
        <div style="margin-bottom:18px; font-size:12px; color:#6b7280;">
            <p style="margin:0 0 4px;"><strong>Reactivación registrada:</strong> {{ $ins->reactivacion->reactivada_at->timezone('America/El_Salvador')->format('d/m/Y H:i') }} (El Salvador), por {{ $ins->reactivacion->administrador?->name ?? 'Administrador registrado' }}.</p>
            <p style="margin:0;">{{ $ins->reactivacion->motivo }}</p>
        </div>
    @endif
    {{-- ENCARGADO (solo si es menor de edad) --}}
    @if($esMenor)
    <div style="margin-bottom:18px;">
        <p style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin:0 0 10px;">Encargado</p>
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px;">
            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px;">
                <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Nombre</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $datosEnc['encargado_nombre'] ?? '—' }}</p></div>
                <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Parentesco</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $datosEnc['encargado_parentesco'] ?? '—' }}</p></div>
                <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">Teléfono</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $datosEnc['encargado_telefono'] ?? '—' }}</p></div>
                <div><p style="font-size:10px; color:#9ca3af; margin:0 0 2px;">DUI</p><p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $datosEnc['encargado_dui'] ?? '—' }}</p></div>
            </div>
        </div>
    </div>
    @endif

    {{-- FIRMAS --}}
    <div>
        <p style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin:0 0 10px;">Firmas</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <p style="font-size:13px; color:#374151; margin:0;">✒ Firma del alumno</p>
            @if($esMenor)
            <p style="font-size:13px; color:#374151; margin:0;">✒ Firma del encargado</p>
            @endif
            @if($ins->documento_path)
            <a href="{{ config('documentos_inscripcion.enabled') ? route('admin.solicitudes.documento', $ins) : asset('storage/'.$ins->documento_path) }}" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex; align-items:center; gap:6px; align-self:flex-start; padding:8px 14px; background:#f3f4f6; border-radius:8px; font-size:12px; font-weight:600; color:#374151; text-decoration:none;"
               onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Ver documento con firmas
            </a>
            @else
            <p style="font-size:12px; color:#9ca3af; margin:0;">No hay documento de firmas adjunto.</p>
            @endif
        </div>
    </div>
</div>
@endforeach

{{-- ═══ MODAL DETALLE ═══ --}}
<div id="modalDetalle" data-reabrir="{{ $errors->any() ? old('reactivacion_contexto', '') : '' }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:520px; max-height:88vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Detalle de la solicitud</p>
            </div>
            <button onclick="cerrarDetalle()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; color:#6b7280;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>
        <div id="cuerpoDetalle"></div>
    </div>
</div>

<style>
    .filtro-estado {
        padding:6px 14px; font-size:12px; font-weight:600; border:1px solid #e5e7eb;
        background:#f9fafb; color:#374151; border-radius:8px; cursor:pointer;
    }
    .filtro-estado:hover { background:#eef2f7; }
    .filtro-estado.activo { background:#111827; color:#fff; border-color:#111827; }
</style>

@include('admin.partials.modal-rechazo-solicitud')

@push('scripts')
@vite('resources/js/admin/solicitudes.js')
@endpush
<x-logout-modal />
</x-app-layout>
