@php
    $bloque = is_array($bloque ?? null) ? $bloque : [];
    foreach (['dia_semana', 'hora_inicio', 'hora_fin'] as $campoHorario) {
        if (!is_scalar($bloque[$campoHorario] ?? null)) $bloque[$campoHorario] = '';
    }
@endphp
<div class="horario-bloque" data-horario-bloque>
    <div class="horario-dia">
        <label for="horario-{{ $indice }}-dia">Día</label>
        <select id="horario-{{ $indice }}-dia" name="horarios[{{ $indice }}][dia_semana]" required>
            <option value="">Seleccionar día</option>
            @foreach(\App\Services\HorariosCurso::DIAS as $numero => $dia)
                <option value="{{ $numero }}" @selected((string)($bloque['dia_semana'] ?? '') === (string)$numero)>{{ $dia }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="horario-{{ $indice }}-inicio">Hora inicial</label>
        <input type="time" id="horario-{{ $indice }}-inicio" name="horarios[{{ $indice }}][hora_inicio]" value="{{ substr($bloque['hora_inicio'] ?? '', 0, 5) }}" required step="60">
    </div>
    <div>
        <label for="horario-{{ $indice }}-fin">Hora final</label>
        <input type="time" id="horario-{{ $indice }}-fin" name="horarios[{{ $indice }}][hora_fin]" value="{{ substr($bloque['hora_fin'] ?? '', 0, 5) }}" required step="60">
    </div>
    <button type="button" class="horario-quitar" data-quitar-horario aria-label="Quitar este bloque de horario">Quitar</button>
</div>
