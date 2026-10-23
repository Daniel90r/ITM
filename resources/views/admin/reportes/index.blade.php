<x-admin-reportes-layout>
    @push('styles')
        <style>
            .reportes-cards.reportes-portada{display:flex;flex-wrap:wrap}
            .reportes-portada .reportes-card{flex:1 1 calc(33.333% - 12px);min-width:0;box-sizing:border-box}
            @media(max-width:1023px){.reportes-portada .reportes-card{flex-basis:calc(50% - 12px)}}
            @media(max-width:520px){.reportes-portada .reportes-card{flex-basis:100%}}
        </style>
    @endpush
    <x-slot:encabezado>
        <x-boton-regreso :href="route('admin.dashboard')">Volver al panel principal</x-boton-regreso>
        <h1 class="text-2xl font-bold mt-2">Reportes</h1>
        <p class="text-sm text-gray-500">Selecciona un reporte para comenzar</p>
    </x-slot:encabezado>
            <nav class="reportes-cards reportes-portada" aria-label="Tipos de reporte">
                @foreach(array_diff_key($tipos, ['calificaciones' => true]) as $clave => $titulo)
                    <a class="reportes-card" href="{{ route('admin.reportes.show', $clave) }}">
                        <span class="text-xs text-gray-500">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="block font-semibold mt-1">{{ $titulo }} <span aria-hidden="true">↗</span></span>
                    </a>
                @endforeach
            </nav>
</x-admin-reportes-layout>
