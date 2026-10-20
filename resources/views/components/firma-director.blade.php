@props(['contexto', 'bolsaErrores' => 'default'])
<div data-firma-director style="margin-bottom:16px;">
    <label for="firma-modo-{{ $contexto }}" style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">Firma del Director</label>
    <select id="firma-modo-{{ $contexto }}" name="firma_origen" data-firma-modo style="width:100%; padding:9px 11px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; margin-bottom:10px;">
        <option value="archivo">Subir una imagen</option>
        <option value="dibujar" @selected(old('firma_origen') === 'dibujar')>Dibujar firma</option>
    </select>
    <div data-firma-archivo>
        <input type="file" name="firma" aria-label="Imagen de firma del Director" accept="image/png,image/jpeg,image/webp" style="width:100%; font-size:12px;">
        <p style="margin:6px 0; font-size:11px; color:#6b7280;">PNG, JPG o WEBP. Máximo 2 MB. {{ $contexto === 'registro' ? 'La firma es opcional al registrar.' : 'Selecciona una imagen o dibuja una nueva firma.' }}</p>
    </div>
    <div data-firma-dibujo hidden>
        <p style="font-size:12px; color:#6b7280; margin:0 0 8px;">Dibuja con el mouse, el dedo o un lápiz digital. Si el formulario muestra un error, vuelve a dibujar antes de enviarlo.</p>
        <canvas width="800" height="240" aria-label="Dibujar firma del Director" style="display:block; width:100%; height:auto; aspect-ratio:10 / 3; border:1px solid #d1d5db; border-radius:8px; background:#ffffff; touch-action:none; cursor:crosshair;">Puedes subir una imagen como alternativa.</canvas>
        <input type="hidden" name="firma_dibujada" value="" disabled>
        <button type="button" data-firma-limpiar style="margin-top:8px; padding:6px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#6b7280; font-size:12px; cursor:pointer;">Limpiar firma</button>
    </div>
    @foreach(['firma', 'firma_dibujada', 'firma_origen'] as $campo)
        @error($campo, $bolsaErrores) <p role="alert" style="font-size:12px; color:#dc2626; margin:6px 0;">{{ $message }}</p> @enderror
    @endforeach
</div>
@once
    @push('scripts')
        @vite('resources/js/admin/firma-director.js')
    @endpush
@endonce
