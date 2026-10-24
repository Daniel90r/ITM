<nav class="hm-finance-nav" aria-label="Opciones de Pagos">
    <a href="{{ route('admin.pagos') }}" @if(request()->routeIs('admin.pagos')) aria-current="page" @endif>Pagos realizados</a>
    <a href="{{ route('admin.pagos.historial.index') }}" @if(!request()->routeIs('admin.pagos')) aria-current="page" @endif>Estados de cuenta</a>
</nav>
