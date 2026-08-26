<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">

    @include('components.admin-sidebar', ['active' => 'maestros'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">

        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de Maestros</h2>
                     <p style="font-size:12px; color:#6b7280; margin:0;">{{ $totalMaestros }} maestros registrados</p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
                <button type="button" onclick="abrirModalMaestro()"
                    style="background:#111827; border:none; padding:8px 18px; border-radius:8px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;"
                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                    + Nuevo Maestro
                </button>
            </div>
        </header>

        {{-- Buscador --}}
        <div style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; gap:12px; flex-shrink:0;">
            <div style="position:relative; flex:1; max-width:360px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="buscadorMaestro" placeholder="Buscar maestro..."
                    style="width:100%; padding:9px 14px 9px 34px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none;"
                    oninput="filtrarMaestros()"
                    onfocus="this.style.borderColor='#111827'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
            <p id="contadorMaestros" style="font-size:12px; color:#9ca3af; margin:0;"></p>
            <button type="button" onclick="limpiarBusquedaMaestros()"
                style="background:none; border:1px solid #e5e7eb; color:#6b7280; font-size:12px; padding:8px 14px; border-radius:8px; cursor:pointer;"
                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                ✕ Limpiar
            </button>
        </div>

        <div style="flex:1; overflow-y:auto; padding:24px;">

            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                    <div style="width:4px; height:16px; background:#111827; border-radius:2px;"></div>
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Listado de Maestros</p>
                </div>

                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Código</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Nombre</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Materias asignadas</th>
                            <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Estado</th>
                            <th style="padding:11px 24px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($maestros as $maestro)
                        <tr class="fila-maestro"
                            data-nombre="{{ strtolower($maestro->nombre_completo) }}"
                            style="border-top:1px solid #f1f5f9; background:{{ $loop->even ? '#f8fafc' : '#ffffff' }};"
                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='{{ $loop->even ? '#f8fafc' : '#ffffff' }}'">
                            <td style="padding:13px 24px; color:#6b7280; font-size:13px; font-family:monospace; font-weight:600;">
                                {{ $maestro->codigo }}
                            </td>
                            <td style="padding:13px 24px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:34px; height:34px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                        {{ strtoupper(substr($maestro->nombre,0,1).substr($maestro->apellido,0,1)) }}
                                    </div>
                                    <div>
                                        <p style="font-size:13px; font-weight:500; color:#111827; margin:0;">{{ $maestro->nombre_completo }}</p>
                                        <p style="font-size:11px; color:#9ca3af; margin:0;">{{ $maestro->detalleCursos->count() }} asignaciones</p>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:13px 24px;">
                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                    @forelse($maestro->detalleCursos->take(3) as $detalle)
                                        <span style="background:#f3f4f6; color:#374151; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:500;">
                                            {{ $detalle->materia->nombre ?? '—' }}
                                        </span>
                                    @empty
                                        <span style="font-size:12px; color:#9ca3af;">Sin asignaciones</span>
                                    @endforelse
                                    @if($maestro->detalleCursos->count() > 3)
                                        <span style="background:#e5e7eb; color:#6b7280; padding:2px 8px; border-radius:4px; font-size:11px;">
                                            +{{ $maestro->detalleCursos->count() - 3 }} más
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td style="padding:13px 24px;">
                                @if($maestro->activo)
                                    <span style="background:#f0fdf4; color:#16a34a; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">● Activo</span>
                                @else
                                    <span style="background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">● Inactivo</span>
                                @endif
                            </td>
                            <td style="padding:13px 24px; text-align:center; display:flex; gap:6px; justify-content:center;">
                                <a href="{{ route('admin.maestros.show', $maestro->id) }}"
                                    style="background:#111827; border:none; color:#fff; cursor:pointer; font-size:12px; font-weight:600; padding:6px 14px; border-radius:6px; text-decoration:none;"
                                    onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                                    Asignar cursos
                                </a>
                                <button type="button" onclick="abrirModalEditarMaestro(@js($maestro->id), @js($maestro->nombre), @js($maestro->apellido), @js($maestro->telefono), @js($maestro->dui), @js($maestro->codigo), @js($maestro->user?->email ?? ''))"
                                    style="background:#eff6ff; border:none; color:#3b82f6; cursor:pointer; font-size:12px; font-weight:600; padding:6px 14px; border-radius:6px;"
                                    onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                    Editar
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="padding:40px; text-align:center; color:#9ca3af; font-size:13px;">No hay maestros registrados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <div id="sinResultados" style="display:none; padding:40px; text-align:center; color:#9ca3af; font-size:13px;">
                    No se encontraron maestros para tu búsqueda.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Conectar modal desde archivo separado --}}
@include('admin.partials.modal-nuevo-maestro')
@include('admin.partials.modal-editar-maestro')

@push('scripts')
@vite('resources/js/admin/maestros.js')
@endpush
<x-logout-modal />
</x-app-layout>
