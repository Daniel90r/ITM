@php
    $conductaSeleccionada = old('notas.'.$alumno->id.'.conducta', $nota?->conducta);
    if ($conductaSeleccionada !== null && !is_string($conductaSeleccionada)) $conductaSeleccionada = '__invalida__';
@endphp
<select name="notas[{{ $alumno->id }}][conducta]" class="nota-input"
        aria-label="Conducta de {{ $alumno->nombre }} {{ $alumno->apellido }}"
        style="width:160px; max-width:100%; text-align:center; background:#ffffff; border:1px solid #d1d5db; border-radius:7px; padding:6px; color:#ef4444; font-size:13px; outline:none;"
        onfocus="this.style.borderColor='#ef4444'" onblur="this.style.borderColor='#d1d5db'">
    @if(filled($conductaSeleccionada) && !array_key_exists($conductaSeleccionada, \App\Support\Conducta::OPCIONES))
    {{-- Valor anterior visible como inválido, no disponible como opción nueva.
         No se borra al enviar otras notas: el servidor exige elegir un valor permitido. --}}
    <option value="{{ $conductaSeleccionada }}" selected hidden>Sin evaluación válida (registro anterior)</option>
    @endif
    <option value="" {{ !filled($conductaSeleccionada) ? 'selected' : '' }}>Pendiente de evaluación</option>
    @foreach(\App\Support\Conducta::OPCIONES as $codigo => $nombreConducta)
    <option value="{{ $codigo }}" {{ $conductaSeleccionada === $codigo ? 'selected' : '' }}>{{ $nombreConducta }}</option>
    @endforeach
</select>
@error('notas.'.$alumno->id.'.conducta')
<p role="alert" style="font-size:12px; color:#991b1b; margin-top:4px;">{{ $message }}</p>
@enderror
