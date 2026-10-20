<?php

namespace Tests\Concerns;

use App\Models\Alumno;
use App\Models\Encargado;

trait ConEncargadoOficial
{
    private function prepararEncargadoOficial(Alumno $alumno): void
    {
        // Edad determinista: estas regresiones también ejercitan el flujo del menor.
        $alumno->update(['fecha_nacimiento' => now()->subYears(16)->toDateString()]);
        $encargado = Encargado::create([
            'nombre' => 'Encargado', 'apellido' => 'Oficial',
            'dui' => '12345678-9', 'telefono' => null, 'activo' => true,
        ]);
        $alumno->encargados()->attach($encargado->id, ['parentesco' => 'Madre']);
    }
}
