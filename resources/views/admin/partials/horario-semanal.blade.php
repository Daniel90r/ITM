@php
    $gestorHorarios = app(\App\Services\HorariosCurso::class);
    $horariosHabilitados = $gestorHorarios->disponible();
    $bloquesGuardados = isset($curso) ? $gestorHorarios->cargar($curso)->horarios->toArray() : [];
    $bloques = session()->hasOldInput('horarios_presentes') ? old('horarios', []) : $bloquesGuardados;
    $bloques = is_array($bloques) ? array_values($bloques) : [];
    $horarioSoloLectura = isset($curso) && $curso->estado_curso === \App\Models\Curso::ESTADO_FINALIZADO;
@endphp
<fieldset class="horario-semanal" data-horario-semanal data-siguiente-indice="{{ count($bloques) }}" @disabled(!$horariosHabilitados || $horarioSoloLectura)>
    <legend>Horario semanal</legend>
    <p class="horario-ayuda">Agrega los días y horas de clase. Puedes dejar el horario sin configurar. No se permiten bloques que crucen medianoche.</p>
    @if(!$horariosHabilitados)
        <p class="horario-aviso" role="status">La configuración de horarios estará disponible después de aplicar la migración pendiente.</p>
    @elseif($horarioSoloLectura)
        <p class="horario-aviso">El curso está finalizado; sus horarios no pueden modificarse.</p>
    @endif
    @if($horariosHabilitados)<input type="hidden" name="horarios_presentes" value="1">@endif
    @if($errors->any())
        @foreach($errors->getMessages() as $campo => $mensajes)
            @if(str_starts_with($campo, 'horarios'))
                @foreach($mensajes as $mensaje)<p class="horario-error" role="alert">{{ $mensaje }}</p>@endforeach
            @endif
        @endforeach
    @endif
    <div data-horario-lista>
        @foreach($bloques as $indice => $bloque)
            @include('admin.partials.horario-bloque', compact('indice', 'bloque'))
        @endforeach
    </div>
    <p class="horario-vacio" data-horario-vacio @if(count($bloques)) hidden @endif>Horario no configurado</p>
    <button type="button" class="horario-agregar" data-agregar-horario>+ Agregar bloque</button>
    <span class="sr-only" aria-live="polite" data-horario-status></span>
    <template data-horario-template>
        @include('admin.partials.horario-bloque', ['indice' => '__INDEX__', 'bloque' => []])
    </template>
</fieldset>
