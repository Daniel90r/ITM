<x-app-layout>
<div style="max-width:800px; margin:0 auto; padding:30px; font-family:Arial, sans-serif;">

    <div style="text-align:center; margin-bottom:30px; border-bottom:2px solid #3b82f6; padding-bottom:20px;">
        <h1 style="font-size:24px; font-weight:bold; color:#1f2937; margin:0;">Reporte de Asistencia</h1>
        <p style="font-size:14px; color:#6b7280; margin:10px 0 0;">ITM Aguilares - Sistema Académico</p>
    </div>

    <div style="margin-bottom:20px;">
        <h2 style="font-size:18px; font-weight:600; color:#1f2937; margin:0;">
            {{ $detalle->materia->nombre }}@if($detalle->materia->es_opcional) (opcional)@endif
        </h2>
        <p style="font-size:14px; color:#6b7280; margin:10px 0 0;">
            <strong>Curso:</strong> {{ $curso->seccion }} — {{ $curso->nombre }} ({{ $curso->nivel }})
        </p>
        <p style="font-size:14px; color:#6b7280; margin:10px 0 0;">
            <strong>Docente:</strong> {{ $maestro->nombre_completo }}
        </p>
        <p style="font-size:14px; color:#6b7280; margin:5px 0 0;">
            <strong>Fecha:</strong> {{ $fechaFormateada }}
        </p>
        <p style="font-size:14px; color:#6b7280; margin:5px 0 0;">
            <strong>Total alumnos:</strong> {{ $alumnos->count() }}
        </p>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead>
            <tr style="background:#f3f4f6; border-bottom:2px solid #e5e7eb;">
                <th style="padding:12px 8px; text-align:left; color:#374151;">#</th>
                <th style="padding:12px 8px; text-align:left; color:#374151;">Alumno</th>
                <th style="padding:12px 8px; text-align:left; color:#374151;">Código</th>
                <th style="padding:12px 8px; text-align:center; color:#374151;">Estado</th>
                <th style="padding:12px 8px; text-align:left; color:#374151;">Observación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($alumnos as $i => $alumno)
            @php $estado = $asistencias->get($alumno->id)?->estado ?? 'presente'; @endphp
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px 8px; color:#9ca3af;">{{ $i + 1 }}</td>
                <td style="padding:10px 8px; font-weight:500; color:#1f2937;">{{ $alumno->nombre_completo }}</td>
                <td style="padding:10px 8px; font-family:monospace; color:#6b7280;">{{ $alumno->codigo }}</td>
                <td style="padding:10px 8px; text-align:center;">
                    @if($estado === 'ausente')
                        <span style="color:#dc2626; font-weight:600;">Ausente</span>
                    @elseif($estado === 'permiso')
                        <span style="color:#d97706; font-weight:600;">Ausente con permiso</span>
                    @else
                        <span style="color:#16a34a; font-weight:600;">Presente</span>
                    @endif
                </td>
                <td style="padding:10px 8px; color:#6b7280;">{{ $asistencias->get($alumno->id)?->observacion ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:30px; padding-top:20px; border-top:1px solid #e5e7eb; text-align:center;">
        <p style="font-size:12px; color:#9ca3af; margin:0;">Reporte generado el {{ now()->isoFormat('D [de] MMMM YYYY [a las] HH:mm') }}</p>
        <button onclick="window.print()" style="margin-top:15px; padding:10px 20px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">
            🖨️ Imprimir reporte
        </button>
    </div>

</div>

<style>
@media print {
    button { display: none !important; }
    x-app-layout > div { padding: 0 !important; }
}
</style>
</x-app-layout>