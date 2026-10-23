<x-admin-reportes-layout>
    <x-slot:encabezado>
        <x-boton-regreso :href="route('admin.reportes')">Volver a Reportes</x-boton-regreso>
        <h1 class="text-2xl font-bold mt-2">Reporte de {{ $tipos[$tipo] }}</h1>
    </x-slot:encabezado>
    @include('admin.reportes.partials.contenido')
</x-admin-reportes-layout>
