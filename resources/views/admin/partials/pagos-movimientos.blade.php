<div class="hm-table" tabindex="0" role="region" aria-label="Movimientos de pagos registrados">
<table @class(['pm-table' => $botonesConsulta ?? false])><caption class="sr-only">Pagos efectivamente registrados, con fecha de pago y período de cuota diferenciados</caption>
    <thead><tr><th scope="col">Pago</th><th scope="col">Alumno</th><th scope="col">Curso histórico de la cuota</th><th scope="col">Período de la cuota</th><th scope="col">Fecha de pago</th><th scope="col">Importe pagado</th><th scope="col">Observación</th><th scope="col">Consulta</th></tr></thead>
    <tbody>@forelse($pagos as $pago)
        <tr data-pago="{{ $pago->id }}"><td>#{{ $pago->id }}</td><td>{{ $pago->nombre }} {{ $pago->apellido }}<br><small>{{ $pago->codigo }}</small></td>
        <td>{{ $pago->curso_nombre ?? ($pago->curso_id === null ? 'Sin curso asociado' : 'Curso no disponible') }}</td>
        <td>{{ $pago->cuota_mes }} / {{ $pago->cuota_anio }}</td><td>{{ $pago->fecha_pago?->format('d/m/Y') ?? 'Sin fecha registrada' }}</td>
        <td>${{ number_format($pago->monto_pagado, 2) }}@if($pago->cuota_estado !== 'Pagado')<p class="hm-warning">La cuota no figura pagada; requiere revisión.</p>@endif</td>
        <td class="hm-payment-note">{{ $pago->observacion ?: '—' }}</td><td @class(['pm-consult-cell' => $botonesConsulta ?? false])><a @class(['hm-btn pm-action pm-secondary pm-consult' => $botonesConsulta ?? false]) href="{{ route('admin.pagos.estado-cuenta', ['alumno' => $pago->alumno_id, 'anio' => $pago->cuota_anio]) }}">@if($botonesConsulta ?? false)<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>@endif<span>Ver estado de cuenta</span></a></td></tr>
    @empty<tr><td colspan="8">No hay pagos registrados para los filtros seleccionados.</td></tr>@endforelse</tbody>
</table></div>
{{ $pagos->links() }}
