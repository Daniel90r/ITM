<x-app-layout>


<div id="portal-alumno" style="display:flex; height:100vh; overflow:hidden;" class="fade-in portal-alumno">

    @include('components.alumno-sidebar', ['active' => 'pagos'])

    <div class="portal-main" style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#ffffff;">

        <header class="portal-header" style="background:#ffffff; border-bottom:1px solid #e5e7eb; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            @include('alumno.partials.menu-toggle')
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="{{ asset('images/logo_itm.jpg') }}" alt="ITM Aguilares" style="width:36px; height:36px; border-radius:10px; object-fit:cover;">
                <div>
                    <h2 style="font-size:16px; font-weight:700; margin:0; color:#1f2937;">Portal Estudiantil</h2>
                    <p style="font-size:12px; color:#6b7280; margin:0;">{{ $alumno->nombre_completo }}</p>
                </div>
            </div>
            <span style="font-size:12px; color:#6b7280;">{{ now()->isoFormat('MMMM YYYY') }}</span>
        </header>

        <div class="portal-content fade-in" style="flex:1; overflow-y:auto; padding:24px;">

            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
                <div style="padding:14px 18px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
                     <p style="font-size:14px; font-weight:600; color:#1f2937; margin:0;">Estado de mensualidades</p>
                    @php
                        $pagados = $mensualidades->where('estado','pagado')->count();
                        $totalMeses = $mensualidades->count();
                    @endphp
                    @if($totalMeses > 0)
                    <span style="font-size:12px; color:#6b7280;">{{ $pagados }}/{{ $totalMeses }} pagados</span>
                    @endif
                </div>

                @if($mensualidades->count() > 0)
                <div>
                    @foreach($mensualidades as $m)
                    <div class="portal-pago" style="display:flex; align-items:center; padding:13px 18px; border-top:1px solid #f3f4f6;"
                         onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        <div style="width:34px; height:34px; border-radius:8px; background:{{ $m->estado === 'pagado' ? '#dcfce7' : '#fee2e2' }}; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-right:14px;">
                            @if($m->estado === 'pagado')
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            @endif
                        </div>
                        <div style="flex:1;">
                            <p style="font-size:13px; font-weight:500; color:#1f2937; margin:0;">{{ $m->mes }} {{ $m->anio }}</p>
                            @if($m->fecha_pago)
                            <p style="font-size:11px; color:#6b7280; margin:2px 0 0;">Pagado el {{ \Carbon\Carbon::parse($m->fecha_pago)->isoFormat('D MMM YYYY') }}</p>
                            @endif
                        </div>
                        @if($m->monto > 0)
                        <p style="font-size:13px; color:#6b7280; margin:0 16px;">${{ number_format($m->monto, 2) }}</p>
                        @endif
                        <span style="font-size:11px; font-weight:600; padding:4px 12px; border-radius:20px;
                            background:{{ $m->estado === 'pagado' ? '#dcfce7' : '#fee2e2' }};
                            color:{{ $m->estado === 'pagado' ? '#16a34a' : '#dc2626' }};">
                            {{ $m->estado === 'pagado' ? 'Pagado' : 'Pendiente' }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="padding:40px; text-align:center;">
                    <p style="color:#6b7280; font-size:13px;">No hay registros de mensualidades aún.</p>
                </div>
                @endif
            </div>

        </div>
    </div>
</div>
</x-app-layout>
