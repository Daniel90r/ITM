<?php

namespace Database\Factories;

use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materia>
 */
class MateriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->word(),
            'codigo' => 'MAT-' . fake()->unique()->numerify('###'),
            'descripcion' => fake()->sentence(),
            'activa' => true,
        ];
    }
}
