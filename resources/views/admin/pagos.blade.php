<x-historial-mensualidades-layout>
@push('styles')
<style>{!! file_get_contents(resource_path('css/admin/pagos-movimientos.css')) !!}</style>
@endpush
<div class="pm-page">
<h1>Pagos realizados</h1>
<p class="hm-note">Solo pagos registrados. La fecha de pago es independiente del mes y año de la cuota. Los filtros de fecha se aplican al pago.</p>
@if(session('success'))<p class="hm-panel" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="hm-warning" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="hm-panel hm-filters" method="GET" action="{{ route('admin.pagos') }}">
    <input type="hidden" name="alumno_id" value="{{ $filtros['alumno_id'] ?? '' }}">
    <div class="pm-search" data-pagos-search data-url="{{ route('admin.pagos.alumnos') }}">
        <label for="pagos-alumno">Alumno o código</label>
        <input id="pagos-alumno" type="search" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pagos-sugerencias">
    </div>
    <label>Año de pago<input type="number" name="anio" value="{{ $filtros['anio'] ?? '' }}" min="1900" max="2100" placeholder="Todos los años" title="Dejar vacío para consultar todos los años"></label>
    <label>Mes de pago<select name="mes"><option value="">Todos los meses</option>@foreach(\App\Services\ReportesAdministrativos::MESES as $numero => $mes)<option value="{{ $numero }}" @selected(($filtros['mes'] ?? '') == $numero)>{{ $mes }}</option>@endforeach</select></label>
    <div class="pm-filter-actions">
        <button type="submit" class="hm-btn pm-action pm-primary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 4h18l-7 8v7l-4 2v-9z"/></svg><span>Aplicar filtros</span></button>
        <a class="hm-btn pm-action pm-secondary" href="{{ route('admin.pagos') }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 11a9 9 0 1 1 3 8M3 4v7h7"/></svg><span>Limpiar</span></a>
    </div>
</form>
<dl class="hm-summary">
    <div><dt>Total pagado en los resultados</dt><dd>${{ number_format($totalRecaudado, 2) }}</dd></div>
    <div><dt>Pagado este mes · {{ now()->format('m/Y') }}</dt><dd>${{ number_format($recaudadoMes, 2) }}</dd></div>
    <div><dt>Pagos este mes en los resultados</dt><dd>{{ $pagosMesActual }}</dd></div>
</dl>
<p class="hm-note">{{ $pagos->total() }} movimientos · Los indicadores incluyen todas las páginas y respetan los filtros. El importe de pagos no se deduce del estado de la cuota.</p>
@include('admin.partials.pagos-movimientos', ['botonesConsulta' => true])
</div>
</x-historial-mensualidades-layout>
