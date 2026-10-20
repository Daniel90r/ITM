<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirmaDirectorService
{
    public function guardar(Request $request, bool $actualizacion = false): ?string
    {
        $bag = $actualizacion ? 'firmaDirector' : 'default';
        $validator = Validator::make($request->all(), [
            'firma_origen' => 'nullable|in:archivo,dibujar',
            'firma' => [$actualizacion ? 'required_without:firma_dibujada' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'prohibits:firma_dibujada'],
            'firma_dibujada' => [$actualizacion ? 'required_without:firma' : 'nullable', 'required_if:firma_origen,dibujar', 'string', 'max:2796226', 'prohibits:firma'],
        ]);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->errorBag($bag);
        }

        $dibujada = $request->filled('firma_dibujada');
        if (!$dibujada && !$request->hasFile('firma')) return null;
        $campo = $dibujada ? 'firma_dibujada' : 'firma';
        $fallar = function () use ($campo, $bag): never {
            throw ValidationException::withMessages([
                $campo => 'La firma debe ser una imagen válida, contener trazos visibles y pesar como máximo 2 MB. Vuelve a dibujar o selecciona otra imagen.',
            ])->errorBag($bag);
        };

        if ($dibujada) {
            $data = $request->input('firma_dibujada');
            if (!str_starts_with($data, 'data:image/png;base64,')) $fallar();
            $bytes = base64_decode(substr($data, 22), true);
        } else {
            $bytes = file_get_contents($request->file('firma')->getRealPath());
        }
        if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024) $fallar();
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
            || ($dibujada && $info[2] !== IMAGETYPE_PNG)
            || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 2000000) $fallar();
        $imagen = @imagecreatefromstring($bytes);
        if (!$imagen) $fallar();
        try {
            $tinta = 0;
            for ($y = 0; $y < imagesy($imagen) && $tinta < 8; $y++) {
                for ($x = 0; $x < imagesx($imagen) && $tinta < 8; $x++) {
                    $color = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));
                    if ($color['alpha'] < 120 && min($color['red'], $color['green'], $color['blue']) < 220) $tinta++;
                }
            }
            if ($tinta < 8) $fallar();
            // Normalizar a PNG, descartando metadatos y contenido adjunto.
            imagesavealpha($imagen, true);
            ob_start();
            try { imagepng($imagen); $png = ob_get_contents(); }
            finally { ob_end_clean(); }
        } finally { imagedestroy($imagen); }
        if (!is_string($png) || $png === '' || strlen($png) > 2 * 1024 * 1024) $fallar();

        $path = 'directores/firmas/'.Str::uuid().'.png';
        if (!Storage::disk('local')->put($path, $png)) {
            throw ValidationException::withMessages(['firma' => 'No se pudo guardar la firma. Inténtalo de nuevo.'])->errorBag($bag);
        }
        return $path;
    }
}
