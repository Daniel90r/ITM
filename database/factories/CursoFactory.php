<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Curso ' . fake()->randomLetter(),
            'nivel' => fake()->randomElement(['Matutino', 'Vespertino']),
            'seccion' => fake()->randomLetter(),
            'anio_lectivo' => date('Y'),
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addMonths(6),
            'activo' => true,
            'finalizado_manualmente' => false,
        ];
    }
}
