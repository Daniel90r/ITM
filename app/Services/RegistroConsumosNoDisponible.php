<?php

namespace App\Services;

use App\Contracts\RegistroConsumosIndependiente;
use RuntimeException;

/** No se entrega un adaptador real ni se selecciona mediante variables/flags. */
class RegistroConsumosNoDisponible implements RegistroConsumosIndependiente
{
    public function exigirDisponibleParaCanalReal(): void
    {
        throw new RuntimeException('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE');
    }

    public function consultar(array $solicitud): array
    {
        throw new RuntimeException('REGISTRO_INDEPENDIENTE_NO_DISPONIBLE');
    }

    public function consumir(array $solicitud): array
    {
        throw new RuntimeException('REGISTRO_INDEPENDIENTE_NO_DISPONIBLE');
    }
}
