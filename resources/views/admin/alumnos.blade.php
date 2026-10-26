<x-app-layout>
@push('styles')
<style>
    .alumnos-panel { min-width:0; }
    .alumnos-scroll { width:100%; overflow-x:auto; }
    .alumnos-table { width:100%; min-width:1100px; border-collapse:collapse; }
    .alumnos-table th, .alumnos-table td { vertical-align:middle; line-height:1.45; }
    .alumnos-table th { white-space:nowrap; }
    .alumnos-table td:first-child, .alumnos-table td:nth-child(3),
    .alumnos-table td:nth-child(5), .alumnos-table td:nth-child(6),
    .alumnos-table td:nth-child(7) { white-space:nowrap; }
    .alumnos-table td:nth-child(2) { min-width:220px; white-space:normal; }
    .alumnos-identidad { min-width:190px; max-width:280px; }
    .alumnos-nombre { min-width:0; font-weight:600; overflow-wrap:anywhere; }
    .alumnos-table td:nth-child(4) { width:210px; }
    .alumnos-email { display:block; min-width:150px; max-width:210px; white-space:normal; overflow-wrap:anywhere; }
    .alumnos-table td:nth-child(5) span,
    .alumnos-table td:nth-child(6) span { display:inline-flex; align-items:center; white-space:nowrap; }
    .alumnos-table td:last-child button { display:inline-flex; align-items:center; justify-content:center; min-height:32px; white-space:nowrap; }
    .alumnos-filtros > * { min-width:0; }
    .alumnos-panel .alumnos-filtros-panel{padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;margin:0;min-width:0}
    .alumnos-panel .alumnos-filtros{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .alumnos-panel .alumnos-filtro-campo{min-width:0}
    .alumnos-panel .alumnos-filtro-campo label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px}
    .alumnos-panel .alumnos-filtro-campo :is(input,select){box-sizing:border-box;width:100%;min-width:0;min-height:42px;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;background:white;color:#0f172a;font-size:13px}
    .alumnos-panel .alumnos-filtro-historicos{margin:10px 0 12px}
    .alumnos-panel .alumnos-filtro-historicos [data-consulta-cursos-alcance]>p{display:none}
    .alumnos-panel #alumnos-alcance .alumnos-filtros-busqueda{display:grid;grid-template-columns:minmax(0,1fr) auto!important;gap:12px;align-items:end}
    .alumnos-panel .alumnos-limpiar{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;background:white;border:1px solid #cbd5e1;border-radius:8px;color:#334155;font-size:13px;font-weight:600;white-space:nowrap;cursor:pointer}
    .alumnos-panel .alumnos-limpiar:hover{background:#e2e8f0;border-color:#94a3b8}
    .alumnos-panel .alumnos-filtro-nota{margin:10px 0 0;color:#64748b;font-size:12px;line-height:1.5}
    .alumnos-panel .alumnos-filtros-panel :is(input,select,button):focus-visible{outline:3px solid #60a5fa;outline-offset:3px}
    @media(max-width:1000px) {
        .alumnos-filtros { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
        .alumnos-resumen { gap:8px !important; }
        .alumnos-resumen > div { padding:12px !important; gap:8px !important; }
    }
    @media(max-width:600px) {
        .alumnos-contenido { padding:14px !important; }
        .alumnos-resumen { grid-template-columns:1fr !important; }
        .alumnos-filtros { grid-template-columns:minmax(0,1fr) !important; }
        .alumnos-panel #alumnos-alcance .alumnos-filtros-busqueda,.alumnos-panel #alumnos-alcance .alumnos-filtros{grid-template-columns:minmax(0,1fr)!important}
        .alumnos-encabezado { flex-wrap:wrap; gap:10px; }
    }
</style>
@endpush
<div style="display:flex; height:100vh; overflow:hidden;">
{{-- Esta es la gestion de alumnos  --}}
    @include('components.admin-sidebar', ['active' => 'alumnos'])

    <div class="alumnos-panel" style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de Alumnos</h2>
                    <p data-alumnos-contador style="font-size:12px; color:#6b7280; margin:0;" id="contadorHeader" data-total="{{ $alumnos->count() }} alumnos inscritos">{{ $alumnos->count() }} alumnos inscritos</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="alumnos-contenido" style="flex:1; min-width:0; overflow-y:auto; padding:24px;">

            @include('admin.partials.alumnos-tabs')

            @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; border-radius:10px; padding:12px 16px; font-size:13px; color:#16a34a; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; font-size:13px; color:#dc2626; margin-bottom:16px;">
                {{ session('error') }}
            </div>
            @endif

            {{-- Aqui se muestra las estadísticas --}}
            <div data-alumnos-resumen class="alumnos-resumen" style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px;">
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $totalAlumnos }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Total alumnos</p>
                    </div>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $activos }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Activos</p>
                    </div>
                </div>
                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#fef2f2; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div>
                        <p style="font-size:22px; font-weight:700; color:#111827; margin:0;">{{ $inactivos }}</p>
                        <p style="font-size:12px; color:#6b7280; margin:0;">Inactivos</p>
                    </div>
                </div>
            </div>

            {{-- Tabla --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.04);">

                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb;">
                    <div class="alumnos-encabezado" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="width:4px; height:18px; background:#3b82f6; border-radius:2px;"></div>
                            <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Listado de Alumnos</p>
                        </div>
                        <button onclick="abrirModalNuevo()"
                            style="background:#3b82f6; border:none; padding:8px 16px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;"
                            onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                            + Nuevo Alumno
                        </button>
                    </div>

                    <form id="alumnos-alcance" method="GET" action="{{ route('admin.alumnos') }}" class="alumnos-filtros-panel" aria-label="Filtros del listado de alumnos">
                        <div class="alumnos-filtros">
                            <div class="alumnos-filtro-campo">
                                <label for="alumnos-curso-consulta">Curso</label>
                                <select id="alumnos-curso-consulta" name="curso_id" onchange="this.form.requestSubmit()"><x-consulta-cursos-opciones :cursos="$cursosFiltro" :filtros="$filtrosCursos" /></select>
                            </div>
                            <div class="alumnos-filtro-campo">
                                <label for="filtrarAnio">Año lectivo</label>
                                <select id="filtrarAnio" name="anio" form="alumnos-alcance" onchange="filtrarAlumnos()">
                                    <option value="">Todos los años</option>
                                    @php
                                        $aniosList = $alumnos->filter(fn($a) => $a->inscripciones->isNotEmpty())
                                            ->map(fn($a) => $a->inscripciones->first()->curso->anio_lectivo ?? null)
                                            ->filter()->unique()->sort()->values();
                                        if (!empty($filtrosCursos['anio']) && !$aniosList->contains($filtrosCursos['anio'])) {
                                            $aniosList = $aniosList->push($filtrosCursos['anio'])->sort()->values();
                                        }
                                    @endphp
                                    @foreach($aniosList as $anio)
                                    <option value="{{ $anio }}" @selected(($filtrosCursos['anio'] ?? '') == $anio)>{{ $anio }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="alumnos-filtro-historicos"><x-consulta-cursos-alcance :filtros="$filtrosCursos" /></div>
                        <div class="alumnos-filtros-busqueda">
                            <div class="alumnos-filtro-campo">
                                <label for="buscarAlumno">Buscar alumno</label>
                                <input type="text" id="buscarAlumno" name="q" form="alumnos-alcance" value="{{ $filtrosCursos['q'] ?? '' }}" placeholder="Buscar alumno..."
                                    oninput="filtrarAlumnos()" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                            </div>
                            <button type="button" class="alumnos-limpiar" onclick="limpiarFiltrosAlumnos()">Limpiar filtros</button>
                        </div>
                        <input type="hidden" id="filtrarCurso" value="">
                        <p class="alumnos-filtro-nota">Sin seleccionar curso también se muestran expedientes sin inscripción aprobada.</p>
                        <noscript><button type="submit">Consultar</button><a href="{{ route('admin.alumnos') }}">Limpiar filtros</a></noscript>
                    </form>

                    <p id="contadorResultados" style="font-size:12px; color:#9ca3af; margin:8px 0 0; display:none;"></p>
                    <p data-alumnos-carga role="status" aria-live="polite" style="font-size:12px;color:#64748b;margin:4px 0 0"></p>
                    <p data-alumnos-error role="alert" style="font-size:13px;color:#991b1b;margin:4px 0 0"></p>
                </div>

                <div data-alumnos-resultados aria-live="polite" aria-busy="false">
                <div class="alumnos-scroll" role="region" aria-label="Listado de alumnos" tabindex="0">
                <table class="alumnos-table">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Matrícula</th>
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Nombre</th>
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Teléfono</th>
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Email</th>
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Curso</th>
                            <th style="padding:12px 16px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Estado</th>
                            <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaAlumnos">
                        @foreach($alumnos as $i => $alumno)
                        @php
                            $inscripcionActiva = $alumno->inscripciones->first();
                            $cursoNombre       = $inscripcionActiva?->curso->nombre ?? '—';
                            $cursoId           = $inscripcionActiva?->curso->id ?? '';
                            $anioLectivo       = $inscripcionActiva?->curso->anio_lectivo ?? '';
                        @endphp
                        <tr class="fila-alumno"
                            data-nombre="{{ strtolower($alumno->nombre_completo) }}"
                            data-curso="{{ strtolower($cursoNombre) }}"
                            data-anio="{{ $anioLectivo }}"
                            style="border-top:1px solid #f1f5f9; background:{{ $loop->even ? '#f8fafc' : '#ffffff' }};"
                            onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='{{ $loop->even ? '#f8fafc' : '#ffffff' }}'">
                            <td style="padding:14px 16px; color:#6b7280; font-size:13px; font-family:monospace; font-weight:600;">{{ $alumno->codigo }}</td>
                            <td style="padding:14px 16px;">
                                <div class="alumnos-identidad" style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:30px; height:30px; border-radius:50%; background:#3b82f6; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                        {{ strtoupper(substr($alumno->nombre,0,1).substr($alumno->apellido,0,1)) }}
                                    </div>
                                    <span class="alumnos-nombre" style="font-size:13px; color:#111827;">{{ $alumno->nombre_completo }}</span>
                                </div>
                            </td>
                            <td style="padding:14px 16px;">
                                @if($alumno->telefono)
                                    <span style="font-size:13px; color:#374151;">{{ $alumno->telefono }}</span>
                                @else
                                    <span style="font-size:12px; color:#9ca3af;">Sin teléfono</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; color:#6b7280; font-size:13px;"><span class="alumnos-email">{{ $alumno->user->email ?? '—' }}</span></td>
                            <td style="padding:14px 16px;">
                                <span style="background:#eff6ff; color:#3b82f6; padding:3px 10px; border-radius:5px; font-size:12px; font-weight:500;">{{ $cursoNombre }}</span>
                            </td>
                            <td style="padding:14px 16px;">
                                @if($alumno->activo)
                                    <span style="background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">● Activo</span>
                                @else
                                    <span style="background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">● Inactivo</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; text-align:center;">
                                <button onclick="abrirModalEditar(
                                        {{ $alumno->id }},
                                        '{{ addslashes($alumno->nombre) }}',
                                        '{{ addslashes($alumno->apellido) }}',
                                        '{{ addslashes($alumno->user->email ?? '') }}',
                                        '{{ $cursoId }}',
                                        '{{ addslashes($alumno->telefono ?? '') }}',
                                        '{{ addslashes($cursoNombre) }}'
                                    )"
                                    style="background:#eff6ff; border:none; color:#3b82f6; cursor:pointer; font-size:12px; font-weight:600; padding:5px 14px; border-radius:6px;"
                                    onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                    Editar
                                </button>
                                <a href="{{ route('admin.personas.estado',['alumnos', $alumno->id]) }}" style="display:inline-block;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;color:#173454;">Estado y acceso</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div id="sinResultados" style="display:{{ $alumnos->isEmpty() ? 'block' : 'none' }}; padding:40px; text-align:center; color:#9ca3af; font-size:13px;">
                    No se encontraron alumnos con los filtros seleccionados.
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.modal-nuevo-alumno')
@include('admin.partials.modal-editar-alumno')

@push('scripts')
@vite('resources/js/admin/alumnos.js')
@endpush
<x-logout-modal />
</x-app-layout>
