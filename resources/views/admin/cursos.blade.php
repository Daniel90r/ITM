<x-app-layout>
<div class="page-layout admin-sidebar">

    @include('components.admin-sidebar', ['active' => 'cursos'])

    <div class="main-content main-content-alt">

        <header class="page-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de Cursos</h2>
                     <p style="font-size:12px; color:#6b7280; margin:0;">{{ $cursos->count() }} Cursos registrados</p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>

                {{-- El botón que conecta al modal --}}
                <button onclick="abrirModalCurso()"
                    style="background:#111827; border:none; padding:8px 18px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                     + Nuevo Curso
                </button>
            </div>
        </header>

        {{-- El buscador --}}
        <div style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; gap:12px; flex-shrink:0;">
            <div style="position:relative; flex:1; max-width:360px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="buscadorCurso" placeholder="Buscar curso..."
                    autocomplete="off"
                    style="width:100%; padding:9px 14px 9px 34px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                    oninput="filtrarCursos()"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
            </div>

            <select id="filtroTurno" onchange="filtrarCursos()"
                style="padding:9px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; cursor:pointer;">
                <option value="">Todos los turnos</option>
                <option value="matutino">Matutino</option>
                <option value="vespertino">Vespertino</option>
            </select>

            <p id="contadorCursos" style="font-size:12px; color:#9ca3af; margin:0; white-space:nowrap;"></p>

            <button onclick="limpiarFiltrosCursos()"
                style="background:none; border:1px solid #e5e7eb; color:#6b7280; font-size:12px; padding:8px 14px; border-radius:8px; cursor:pointer; white-space:nowrap;"
                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                ✕ Limpiar
            </button>
        </div>

        <div class="content-body">

            <div class="card-grid" id="gridCursos">
                @foreach($cursos as $curso)
                @php $porcentaje = $curso->inscripciones_count > 0 ? min(round(($curso->inscripciones_count / 40) * 100), 100) : 0; @endphp
                <div class="card card-hover card-curso"
                    data-nombre="{{ strtolower($curso->nombre) }}"
                    data-turno="{{ strtolower($curso->nivel) }}"
                    onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.borderColor='#d1d5db'"
                    onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'; this.style.borderColor='#e5e7eb'">

                    <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:16px;">
                        <div>
                            <span class="badge-gray">{{ $curso->seccion }}</span>
                            <p style="font-size:15px; font-weight:700; color:#111827; margin:6px 0 2px;">{{ $curso->nombre }}</p>
                            <p style="font-size:12px; color:#6b7280; margin:0;">{{ $curso->nivel }} · {{ $curso->anio_lectivo }}</p>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            @switch($curso->estado_curso)
                                @case('PROXIMO')
                                    <span style="background:#eff6ff; color:#1d4ed8; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;">Próximo</span>
                                    @break
                                @case('ACTIVO')
                                    <span style="background:#f0fdf4; color:#16a34a; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;">Activo</span>
                                    @break
                                @case('FINALIZADO')
                                    <span style="background:#fef2f2; color:#dc2626; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;">Finalizado</span>
                                    @break
                                @default
                                    <span style="background:#f3f4f6; color:#6b7280; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;">Sin fechas</span>
                            @endswitch

                            @if($curso->esta_por_finalizar)
                                <span
                                    style="
                                        padding:4px 8px;
                                        border-radius:999px;
                                        background:#fffbeb;
                                        color:#b45309;
                                        font-size:11px;
                                        font-weight:600;
                                        white-space:nowrap;
                                    "
                                >
                                    @if($curso->dias_para_finalizar === 0)
                                        ⚠ Hoy
                                    @elseif($curso->dias_para_finalizar === 1)
                                        ⚠ Mañana
                                    @else
                                        ⚠ {{ $curso->dias_para_finalizar }} días
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>

                    <div style="margin-bottom:16px; display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:11px; color:#6b7280; font-weight:500;">Inicio</span>
                            <strong style="font-size:12px; color:#111827;">{{ $curso->fecha_inicio?->translatedFormat('d \d\e F \d\e Y') ?? 'Sin definir' }}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:11px; color:#6b7280; font-weight:500;">Finaliza</span>
                            <strong style="font-size:12px; color:#111827;">{{ $curso->fecha_fin?->translatedFormat('d \d\e F \d\e Y') ?? 'Sin definir' }}</strong>
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:12px; color:#6b7280; font-weight:500;">Alumnos inscritos</span>
                            <span style="font-size:12px; font-weight:700; color:#111827;">
                                {{ $curso->inscripciones_count }} / 20
                                @if($curso->esta_lleno)
                                    <span style="color:#dc2626;"> · Cupo lleno</span>
                                @else
                                    <span style="color:#16a34a;"> · {{ $curso->cupos_disponibles }} disponible(s)</span>
                                @endif
                            </span>
                        </div>
                        <div class="progress-bar">
                            @php $porcentaje = min(round(($curso->inscripciones_count / 20) * 100), 100); @endphp
                            <div class="progress-fill" style="width:{{ $porcentaje }}%; {{ $curso->esta_lleno ? 'background:#dc2626;' : '' }}"></div>
                        </div>
                        <p style="font-size:11px; color:#9ca3af; margin:4px 0 0; text-align:right;">{{ $porcentaje }}% ocupado</p>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="{{ route('admin.cursos.edit', $curso->id) }}" class="btn-sm">Editar</a>
                        <a href="{{ route('admin.cursos.show', $curso->id) }}" class="btn-black">Ver detalle</a>

                        @if($curso->estado_curso === 'ACTIVO')
                        <form
                            action="{{ route('admin.cursos.finalizar', $curso) }}"
                            method="POST"
                            class="form-finalizar-curso"
                            data-finalizar-confirm="El curso cambiará al estado Finalizado. ¿Deseas continuar?"
                            data-confirm-titulo="¿Estás seguro de finalizar este curso?"
                            data-confirm-boton="Sí, finalizar curso"
                            data-confirm-tipo="peligro"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-finalizar-curso">
                                Finalizar curso
                            </button>
                            <p data-finalizar-error role="alert" style="font-size:12px;color:#b91c1c;margin:6px 0 0;"></p>
                        </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Sin resultados --}}
            <div id="sinResultados" style="display:none; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:40px; text-align:center; color:#9ca3af; font-size:13px; margin-top:4px;">
                No se encontraron cursos para tu búsqueda.
            </div>

        </div>
    </div>
</div>

{{-- Modal nuevo curso desde archivo separado --}}
@include('admin.partials.modal-nuevo-curso')

@push('scripts')
@vite('resources/js/admin/cursos.js')
@endpush
<x-logout-modal />
</x-app-layout>
