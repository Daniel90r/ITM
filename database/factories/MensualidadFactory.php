<?php

namespace Database\Factories;

use App\Models\Mensualidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mensualidad>
 */
class MensualidadFactory extends Factory
{
    protected $model = Mensualidad::class;

    public function definition(): array
    {
        return [
            'alumno_id' => AlumnoFactory::new(),
            'curso_id' => CursoFactory::new(),
            'mes' => (string) fake()->numberBetween(1, 12),
            'anio' => date('Y'),
            'monto' => fake()->randomFloat(2, 100, 500),
            'estado' => 'Pendiente',
            'fecha_vencimiento' => now()->endOfMonth(),
        ];
    }
}
