<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'inscripciones'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">
        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Alumnos inscritos</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $inscripciones->total() }} alumnos con inscripción aprobada</p>
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

            {{-- ═══ LISTADO DE ALUMNOS INSCRITOS (SOLO APROBADOS) ═══ --}}
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px; flex-shrink:0;">
                    <div style="width:4px; height:16px; background:#16a34a; border-radius:2px;"></div>
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Alumnos con inscripción aprobada</p>
                </div>

                <div style="flex:1; min-height:0; overflow:auto;">
                    <table style="width:100%; border-collapse:collapse; min-width:780px;">
                        <thead>
                            <tr style="position:sticky; top:0; z-index:2; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Alumno</th>
                                <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Código</th>
                                <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Curso</th>
                                <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Turno</th>
                                <th style="padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Fecha de inscripción</th>
                                <th style="padding:11px 24px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inscripciones as $ins)
                            <tr style="border-top:1px solid #f1f5f9; background:{{ $loop->even ? '#f8fafc' : '#ffffff' }};"
                                onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='{{ $loop->even ? '#f8fafc' : '#ffffff' }}'">
                                <td style="padding:13px 24px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:32px; height:32px; border-radius:50%; background:#111827; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">
                                            {{ strtoupper(substr($ins->alumno->nombre,0,1).substr($ins->alumno->apellido,0,1)) }}
                                        </div>
                                        <div>
                                            <span style="font-size:13px; font-weight:500; color:#111827;">{{ $ins->alumno->nombre_completo }}</span>
                                            @if($ins->tomara_ingles)
                                                <span style="display:inline-block; margin-left:6px; background:#eff6ff; color:#1d4ed8; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600;">🌐 Inglés</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:13px 24px; color:#6b7280; font-size:13px; font-family:monospace; font-weight:600;">{{ $ins->alumno->codigo }}</td>
                                <td style="padding:13px 24px; font-size:13px; color:#111827; font-weight:500;">{{ $ins->curso->nombre }}</td>
                                <td style="padding:13px 24px; font-size:13px; color:#374151;">{{ $ins->curso->nivel }}</td>
                                <td style="padding:13px 24px; font-size:13px; color:#6b7280;">{{ \Carbon\Carbon::parse($ins->fecha_inscripcion)->format('d/m/Y') }}</td>
                                <td style="padding:13px 24px; text-align:center;">
                                    <form method="POST" action="{{ route('admin.inscripciones.desactivar', $ins->id) }}"
                                          data-confirm="¿Desactivar la inscripción de {{ $ins->alumno->nombre }}?"
                                          data-confirm-titulo="Desactivar inscripción" data-confirm-boton="Sí, desactivar" data-confirm-tipo="peligro">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; font-size:12px; font-weight:600; color:#dc2626; cursor:pointer; padding:5px 14px;"
                                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                            Desactivar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" style="padding:40px; text-align:center; color:#9ca3af; font-size:13px;">No hay alumnos con inscripción aprobada.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 24px; border-top:1px solid #e5e7eb; flex-wrap:wrap; gap:10px; flex-shrink:0;">
                    <span style="font-size:12px; color:#6b7280;">
                        Mostrando {{ $inscripciones->firstItem() }}–{{ $inscripciones->lastItem() }} de {{ $inscripciones->total() }}
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
<x-logout-modal />
</x-app-layout>
