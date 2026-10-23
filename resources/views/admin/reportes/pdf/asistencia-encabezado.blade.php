<div class="cabecera">
    <img class="itm" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/itm.jpg'))) }}" alt="ITM">
    <p class="institucion">Instituto de Computación de Aguilares</p>
    <h1>{{ isset($estadoAplicado) ? 'REPORTE DE ASISTENCIAS' : 'CONTROL DE ASISTENCIA' }}</h1>
    <p class="sub">@isset($estadoAplicado) HISTÓRICO · ESTADO: {{ $estadoAplicado }} · @else ITM Aguilares · @endisset Emitido: {{ $emitido->format('d/m/Y H:i') }}</p>
    <img class="escudo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('images/reportes/escudo-inscripciones.jpg'))) }}" alt="Escudo de El Salvador">
</div>
<div class="pie">ITM Aguilares · {{ isset($estadoAplicado) ? 'Histórico de Asistencias · '.$estadoAplicado : 'Control de Asistencia' }}</div>
