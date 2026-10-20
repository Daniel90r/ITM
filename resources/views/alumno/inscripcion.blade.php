<x-app-layout>
<div id="portal-alumno" style="display:flex; height:100vh; overflow:hidden;" class="fade-in portal-alumno">

    @include('components.alumno-sidebar', ['active' => 'inscripcion'])

    <div class="portal-main" style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#ffffff;">

        <header class="portal-header" style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            @include('alumno.partials.menu-toggle')
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM" style="width:36px; height:36px; border-radius:10px; object-fit:cover;">
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Portal Estudiantil</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $alumno->nombre_completo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="portal-content" style="flex:1; overflow-y:auto; padding:24px;">

            @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; color:#16a34a; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px;">
                <p style="font-weight:700; margin:0 0 8px;">Corrige los siguientes campos:</p>
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li style="margin-bottom:4px;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(config('documentos_inscripcion.enabled') && $inscripcion?->documento_path)
                <p><a class="btn-sm" href="{{ route('alumno.inscripciones.documento', $inscripcion) }}">Descargar documento presentado</a></p>
            @endif

            @if($inscripcion && $inscripcion->estado === 'aprobada')
            {{-- Ya inscrito y aprobado --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; margin-bottom:20px;">
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:46px; height:46px; border-radius:12px; background:#bbf7d0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <p style="font-size:14px; font-weight:600; color:#16a34a; margin:0 0 4px;">¡Inscripción aprobada!</p>
                        <p style="font-size:13px; color:#374151; margin:0;">
                            Sección <strong>{{ $inscripcion->curso->seccion }}</strong> —
                            Turno <strong>{{ $inscripcion->curso->nivel }}</strong> ·
                            {{ \Carbon\Carbon::parse($inscripcion->fecha_inscripcion)->isoFormat('D [de] MMMM YYYY') }}
                        </p>
                        @if($inscripcion->tomara_ingles)
                            <p style="font-size:12px; color:#1d4ed8; margin:4px 0 0;">🌐 Incluye curso de Inglés</p>
                        @endif
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-top:16px;">
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; text-align:center;">
                        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Sección</p>
                        <p style="font-size:20px; font-weight:700; color:#111827; margin:0;">{{ $inscripcion->curso->seccion }}</p>
                    </div>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; text-align:center;">
                        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Turno</p>
                        <p style="font-size:20px; font-weight:700; color:#111827; margin:0;">{{ $inscripcion->curso->nivel }}</p>
                    </div>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; text-align:center;">
                        <p style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin:0 0 6px;">Año lectivo</p>
                        <p style="font-size:20px; font-weight:700; color:#111827; margin:0;">{{ $inscripcion->curso->anio_lectivo }}</p>
                    </div>
                </div>
            </div>

            @elseif($inscripcion && $inscripcion->estado === 'pendiente')
            {{-- Solicitud pendiente --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; margin-bottom:20px;">
                <div style="background:#fefce8; border:1px solid #fcd34d; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:46px; height:46px; border-radius:12px; background:#fef3c7; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <p style="font-size:14px; font-weight:600; color:#d97706; margin:0 0 4px;">Solicitud en revisión</p>
                        <p style="font-size:13px; color:#374151; margin:0;">
                            Tu solicitud para la sección <strong>{{ $inscripcion->curso->seccion }}</strong>
                            está siendo revisada por el administrador. Te notificaremos cuando sea aprobada.
                        </p>
                    </div>
                </div>
            </div>

            @elseif($inscripcion && $inscripcion->estado === 'rechazada')
            {{-- Solicitud rechazada --}}
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:24px; margin-bottom:20px;">
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:46px; height:46px; border-radius:12px; background:#fee2e2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div>
                        <p style="font-size:14px; font-weight:600; color:#dc2626; margin:0 0 4px;">Solicitud rechazada</p>
                        <p style="font-size:13px; color:#374151; margin:0;">
                            @if($inscripcion->motivo_rechazo)
                                Motivo: {{ $inscripcion->motivo_rechazo }}
                            @else
                                Tu solicitud fue rechazada. Puedes enviar una nueva solicitud.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            {{-- Mostrar formulario para nueva solicitud si fue rechazada --}}
            @include('alumno.partials.formulario-inscripcion', ['cursosDisponibles' => $cursosDisponibles, 'alumno' => $alumno])

            @else
            {{-- Sin inscripción — mostrar formulario --}}
            @include('alumno.partials.formulario-inscripcion', ['cursosDisponibles' => $cursosDisponibles, 'alumno' => $alumno])
            @endif

        </div>
    </div>
</div>
</x-app-layout>
