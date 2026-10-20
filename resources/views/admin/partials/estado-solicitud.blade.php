@php
    [$etiquetaEstado, $fondoEstado, $colorEstado] = match ($estado) {
        'pendiente' => ['Pendiente', '#fef3c7', '#d97706'],
        'aprobada' => ['Aprobada', '#f0fdf4', '#16a34a'],
        'rechazada' => ['Rechazada', '#fef2f2', '#dc2626'],
        'inactiva' => ['Inactiva', '#f3f4f6', '#6b7280'],
        default => ['Estado no reconocido', '#f3f4f6', '#6b7280'],
    };
@endphp
<span style="display:inline-flex; align-items:center; gap:6px; background:{{ $fondoEstado }}; color:{{ $colorEstado }}; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap;"><span style="width:7px; height:7px; border-radius:50%; background:currentColor; display:inline-block;"></span>{{ $etiquetaEstado }}</span>
