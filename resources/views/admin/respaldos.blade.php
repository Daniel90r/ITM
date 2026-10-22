<x-app-layout>
<div style="display:flex; height:100vh; overflow:hidden;">
    @include('components.admin-sidebar', ['active' => 'respaldos'])

    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8fafc;">
        <header style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#111827;">Copias de seguridad</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $respaldos->count() }} {{ $respaldos->count() === 1 ? 'copia guardada' : 'copias guardadas' }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#9ca3af; background:#f3f4f6; padding:6px 12px; border-radius:8px;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div style="flex:1; overflow-y:auto; padding:24px;">

            @if(session('success'))
            <div role="status" style="background:#dcfce7; border:1px solid #86efac; border-radius:10px; padding:12px 16px; font-size:13px; color:#16a34a; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div role="alert" style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 16px; font-size:13px; color:#dc2626; margin-bottom:16px;">
                {{ session('error') }}
            </div>
            @endif

            {{-- Generar --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="display:flex; align-items:flex-start; gap:14px; min-width:260px; flex:1;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#dbeafe; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </div>
                    <div>
                        <p style="font-size:14px; font-weight:600; color:#111827; margin:0 0 4px;">Respaldo de la base de datos</p>
                        <p style="font-size:13px; color:#6b7280; margin:0; line-height:1.5;">
                            Guarda en un archivo .sql la información de alumnos, docentes, cursos, inscripciones, notas, asistencias, boletas, mensualidades y pagos.
                            Las sesiones activas y los datos temporales no se incluyen.
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.respaldos.store') }}" onsubmit="const b=this.querySelector('button'); b.disabled=true; b.textContent='Generando…';">
                    @csrf
                    <button type="submit" style="background:#111827; border:none; padding:10px 18px; border-radius:8px; color:#ffffff; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;">
                        + Generar copia de seguridad
                    </button>
                </form>
            </div>

            {{-- Listado --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; margin-bottom:20px;">
                <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:8px;">
                    <span style="width:4px; height:16px; background:#3b82f6; border-radius:2px;"></span>
                    <p style="font-size:14px; font-weight:600; color:#111827; margin:0;">Copias guardadas</p>
                </div>

                @if($respaldos->isEmpty())
                    <div style="padding:40px 20px; text-align:center;">
                        <p style="font-size:14px; font-weight:600; color:#374151; margin:0 0 4px;">Todavía no hay copias de seguridad</p>
                        <p style="font-size:13px; color:#6b7280; margin:0;">Use el botón «Generar copia de seguridad» para crear la primera.</p>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:560px;">
                            <thead>
                                <tr style="background:#f9fafb;">
                                    <th scope="col" style="padding:10px 18px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Archivo</th>
                                    <th scope="col" style="padding:10px 18px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Fecha</th>
                                    <th scope="col" style="padding:10px 18px; text-align:left; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Tamaño</th>
                                    <th scope="col" style="padding:10px 18px; text-align:right; color:#6b7280; font-weight:500; font-size:11px; text-transform:uppercase;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($respaldos as $respaldo)
                                <tr style="border-top:1px solid #f3f4f6;">
                                    <td style="padding:12px 18px; color:#111827; font-family:ui-monospace, monospace; font-size:12px;">{{ $respaldo['nombre'] }}</td>
                                    <td style="padding:12px 18px; color:#374151; white-space:nowrap;">{{ $respaldo['fecha']->isoFormat('D [de] MMMM YYYY, h:mm a') }}</td>
                                    <td style="padding:12px 18px; color:#374151; white-space:nowrap;">{{ $respaldo['bytes'] >= 1048576 ? number_format($respaldo['bytes'] / 1048576, 1) . ' MB' : number_format(max($respaldo['bytes'] / 1024, 0.1), 1) . ' KB' }}</td>
                                    <td style="padding:12px 18px; text-align:right; white-space:nowrap;">
                                        <a href="{{ route('admin.respaldos.descargar', $respaldo['nombre']) }}" style="display:inline-block; background:#eff6ff; color:#2563eb; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">Descargar</a>
                                        <form method="POST" action="{{ route('admin.respaldos.destroy', $respaldo['nombre']) }}" style="display:inline;"
                                              onsubmit="return confirm('¿Eliminar la copia {{ $respaldo['nombre'] }}? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:#fef2f2; color:#dc2626; border:none; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; margin-left:6px;">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Recomendaciones --}}
            <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:14px; padding:16px 20px;">
                <p style="font-size:13px; font-weight:600; color:#92400e; margin:0 0 6px;">Recomendaciones</p>
                <ul style="font-size:13px; color:#78350f; margin:0; padding-left:18px; line-height:1.7;">
                    <li>Genere una copia al menos una vez por semana y siempre antes de actualizar el sistema.</li>
                    <li>Descargue cada copia y guárdela fuera del servidor (memoria USB o almacenamiento institucional en la nube).</li>
                    <li>Para restaurar, importe el archivo .sql en phpMyAdmin (pestaña «Importar») sobre la base de datos del sistema.</li>
                    <li>Los documentos de inscripción y las firmas se guardan como archivos aparte; respalde también la carpeta storage/app desde el administrador de archivos.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
