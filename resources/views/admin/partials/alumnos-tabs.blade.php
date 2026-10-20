<nav aria-label="Gestión de Alumnos" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
    @foreach(['admin.alumnos' => 'Alumnos', 'admin.encargados.index' => 'Encargados'] as $ruta => $etiqueta)
        @php($seleccionada = request()->routeIs($ruta))
        <a href="{{ route($ruta) }}" @if($seleccionada) aria-current="page" @endif
           style="padding:9px 16px; border:1px solid {{ $seleccionada ? '#3b82f6' : '#e5e7eb' }}; border-radius:8px; background:{{ $seleccionada ? '#eff6ff' : '#ffffff' }}; color:{{ $seleccionada ? '#2563eb' : '#6b7280' }}; font-size:13px; font-weight:600; text-decoration:none;">
            {{ $etiqueta }}
        </a>
    @endforeach
</nav>
