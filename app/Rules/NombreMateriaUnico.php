<?php

namespace App\Rules;

use App\Models\Materia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class NombreMateriaUnico implements ValidationRule
{
    public const MENSAJE = 'Ya existe una materia registrada con este nombre. Ingresa un nombre diferente.';

    public function __construct(private readonly ?int $excepto = null) {}

    public static function normalizar(string $nombre): string
    {
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $nombre));
    }

    public static function clave(string $nombre): string
    {
        // unicode_ci del catálogo MySQL ignora mayúsculas y acentos.
        return mb_strtolower(Str::ascii(self::normalizar($nombre)), 'UTF-8');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clave = self::clave($value);
        foreach (Materia::query()->select(['id', 'nombre'])->when($this->excepto !== null, fn ($q) => $q->where('id', '<>', $this->excepto))->cursor() as $materia) {
            if (self::clave($materia->nombre) === $clave) {
                $fail(self::MENSAJE);
                return;
            }
        }
    }
}
