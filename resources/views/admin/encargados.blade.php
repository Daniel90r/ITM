<x-app-layout>
    @push('styles')
        <style>
            #gestion-encargados { display:flex; height:100dvh; overflow:hidden; }
            #gestion-encargados .encargados-contenido { padding:24px; }
            #gestion-encargados .encargados-tabla { width:100%; min-width:880px; border-collapse:collapse; }
            #gestion-encargados th { padding:11px 24px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
            #gestion-encargados td { padding:13px 24px; font-size:13px; color:#374151; }
            #gestion-encargados tbody tr:nth-child(even) { background:#f8fafc; }
            #gestion-encargados tbody tr:hover { background:#eff6ff; }
            #gestion-encargados dialog { width:calc(100% - 32px); max-width:560px; max-height:calc(100dvh - 32px); margin:auto; padding:0; border:1px solid #e5e7eb; border-radius:16px; background:#ffffff; color:#111827; box-shadow:0 20px 60px rgba(0,0,0,.2); overflow-y:auto; }
            #gestion-encargados dialog::backdrop { background:rgba(0,0,0,.5); }
            #gestion-encargados .encargado-detalle { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin:0; }
            #gestion-encargados .encargado-detalle dt { font-size:11px; color:#6b7280; margin-bottom:4px; }
            #gestion-encargados .encargado-detalle dd { padding:9px 12px; margin:0; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827; overflow-wrap:anywhere; }
            @media (max-width:767px) {
                #gestion-encargados { flex-direction:column; }
                #gestion-encargados > aside { width:100% !important; max-height:160px; border-right:0 !important; border-bottom:1px solid #e5e7eb; }
                #gestion-encargados .encargados-contenido { padding:16px; }
                #gestion-encargados .encargado-detalle { grid-template-columns:1fr; }
            }
        </style>
    @endpush

    <div id="gestion-encargados" x-data="{ detalle: {} }">
        @include('components.admin-sidebar', ['active' => 'alumnos'])
        <main style="flex:1; min-width:0; min-height:0; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">
            <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; gap:12px; flex-shrink:0;">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:40px; height:40px; border-radius:12px; border:1px solid #e5e7eb; object-fit:cover;">
                <div>
                    <h1 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Gestión de Alumnos</h1>
                    <p style="font-size:12px; color:#6b7280; margin:0;">Gestión de Encargados · Solo consulta</p>
                </div>
            </header>

            <div class="encargados-contenido" style="flex:1; overflow-y:auto;">
                @include('admin.partials.alumnos-tabs')
                <section aria-labelledby="encargados-titulo" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.04);">
                    <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <div style="width:4px; height:18px; background:#3b82f6; border-radius:2px;"></div>
                            <h2 id="encargados-titulo" style="font-size:14px; font-weight:600; color:#111827; margin:0;">Encargados de alumnos menores de 18 años</h2>
                        </div>
                        <p style="font-size:12px; color:#6b7280; margin:0 0 14px;">{{ $alumnos->total() }} alumno(s) encontrado(s). Se muestran todos sus vínculos, incluidos los encargados inactivos.</p>
                        <form method="GET" action="{{ route('admin.encargados.index') }}" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px;">
                            <div style="flex:1; min-width:180px;">
                                <label for="buscarEncargado" style="display:block; font-size:11px; color:#6b7280; margin-bottom:4px;">Nombre o código del alumno, o nombre del encargado</label>
                                <input id="buscarEncargado" name="q" value="{{ $busqueda }}" maxlength="100" type="search" placeholder="Buscar alumno o encargado..."
                                       style="width:100%; box-sizing:border-box; padding:9px 12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; color:#111827;">
                            </div>
                            <button type="submit" style="background:#3b82f6; border:none; padding:9px 16px; border-radius:8px; color:#ffffff; font-size:12px; font-weight:600; cursor:pointer;">Buscar</button>
                            <a href="{{ route('admin.encargados.index') }}" style="padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; color:#6b7280; text-decoration:none;">Limpiar</a>
                        </form>
                        @error('q') <p role="alert" style="font-size:12px; color:#dc2626; margin:8px 0 0;">{{ $message }}</p> @enderror
                    </div>

                    <div role="region" aria-label="Tabla de encargados, desplazamiento horizontal disponible" tabindex="0" style="overflow-x:auto;">
                        <table class="encargados-tabla">
                            <thead><tr style="background:#f8fafc;">
                                @foreach(['Alumno', 'Código', 'Edad', 'Encargado', 'Parentesco', 'Estado del encargado', 'Acción'] as $titulo)
                                    <th scope="col">{{ $titulo }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @forelse($alumnos as $alumno)
                                    @forelse($alumno->encargados as $encargado)
                                        <tr style="border-top:1px solid #f1f5f9;">
                                            <td style="color:#111827; font-weight:500;">{{ $alumno->nombre_completo }}</td>
                                            <td style="color:#6b7280; font-family:monospace; font-weight:600;">{{ $alumno->codigo }}</td>
                                            <td>{{ (int) \Carbon\Carbon::parse($alumno->fecha_nacimiento)->age }} años</td>
                                            <td>{{ $encargado->nombre_completo }}</td>
                                            <td>{{ $encargado->pivot->parentesco ?: 'Sin parentesco registrado' }}</td>
                                            <td><span style="background:{{ $encargado->activo ? '#f0fdf4' : '#fef2f2' }}; color:{{ $encargado->activo ? '#16a34a' : '#dc2626' }}; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">{{ $encargado->activo ? 'Activo' : 'Inactivo' }}</span></td>
                                            <td>
                                                <button type="button" aria-haspopup="dialog" aria-controls="modalDatosEncargado"
                                                    x-on:click="detalle = {{ \Illuminate\Support\Js::from([
                                                        'alumno' => $alumno->nombre_completo,
                                                        'nombre' => $encargado->nombre,
                                                        'apellido' => $encargado->apellido,
                                                        'dui' => $encargado->dui,
                                                        'telefono' => $encargado->telefono,
                                                        'email' => $encargado->email,
                                                        'parentesco' => $encargado->pivot->parentesco,
                                                        'estado' => $encargado->activo ? 'Activo' : 'Inactivo',
                                                    ]) }}; $refs.modal.showModal()"
                                                    style="background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:6px 12px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">Ver datos</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr style="border-top:1px solid #f1f5f9;">
                                            <td style="color:#111827; font-weight:500;">{{ $alumno->nombre_completo }}</td>
                                            <td style="color:#6b7280; font-family:monospace; font-weight:600;">{{ $alumno->codigo }}</td>
                                            <td>{{ (int) \Carbon\Carbon::parse($alumno->fecha_nacimiento)->age }} años</td>
                                            <td colspan="4" style="color:#9ca3af;">Sin encargado registrado</td>
                                        </tr>
                                    @endforelse
                                @empty
                                    <tr><td colspan="7" style="text-align:center; padding:32px 24px; color:#9ca3af;">No se encontraron alumnos menores de 18 años con estos criterios.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($alumnos->hasPages())
                        <div style="padding:16px 24px; border-top:1px solid #e5e7eb;">{{ $alumnos->links() }}</div>
                    @endif
                </section>
            </div>
        </main>

        <dialog id="modalDatosEncargado" x-ref="modal" aria-labelledby="modalEncargadoTitulo" x-on:click="$event.target === $el && $refs.modal.close()">
            <div style="padding:20px 24px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <h2 id="modalEncargadoTitulo" style="font-size:16px; font-weight:700; margin:0;">Datos del encargado</h2>
                    <p x-text="detalle.alumno" style="font-size:12px; color:#6b7280; margin:4px 0 0; overflow-wrap:anywhere;"></p>
                </div>
                <button type="button" autofocus x-on:click="$refs.modal.close()" aria-label="Cerrar datos del encargado" style="background:#f3f4f6; border:none; width:30px; height:30px; flex-shrink:0; border-radius:8px; color:#6b7280; cursor:pointer;">✕</button>
            </div>
            <div style="padding:24px;">
                <dl class="encargado-detalle">
                    @foreach(['nombre' => 'Nombre', 'apellido' => 'Apellido', 'dui' => 'DUI', 'telefono' => 'Teléfono', 'email' => 'Correo electrónico', 'parentesco' => 'Parentesco', 'estado' => 'Estado'] as $campo => $etiqueta)
                        <div><dt>{{ $etiqueta }}</dt><dd x-text="detalle.{{ $campo }} || 'No registrado'"></dd></div>
                    @endforeach
                </dl>
            </div>
            <div style="padding:16px 24px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end;">
                <button type="button" x-on:click="$refs.modal.close()" style="background:#3b82f6; border:none; padding:9px 18px; border-radius:8px; color:#ffffff; font-size:12px; font-weight:600; cursor:pointer;">Cerrar</button>
            </div>
        </dialog>
    </div>
</x-app-layout>
