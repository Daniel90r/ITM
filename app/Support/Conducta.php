<?php

namespace App\Support;

final class Conducta
{
    public const OPCIONES = ['B' => 'Bueno', 'MB' => 'Muy Bueno', 'E' => 'Excelente'];

    /** Mediana ordinal inferior; un dato pendiente o inválido invalida el conjunto. */
    public static function consolidar(iterable $codigos): string
    {
        $orden = array_keys(self::OPCIONES);
        $valores = [];
        foreach ($codigos as $codigo) {
            if (!is_string($codigo) || !array_key_exists($codigo, self::OPCIONES)) return 'Pendiente de evaluación';
            $valores[] = array_search($codigo, $orden, true);
        }
        if (!$valores) return 'Pendiente de evaluación';
        sort($valores, SORT_NUMERIC);
        return self::OPCIONES[$orden[$valores[intdiv(count($valores) - 1, 2)]]];
    }

    public static function etiqueta(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') return 'Pendiente de evaluación';
        return self::OPCIONES[$codigo] ?? 'Sin evaluación válida';
    }

    public static function reglas(): array
    {
        return ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys(self::OPCIONES))];
    }
}
