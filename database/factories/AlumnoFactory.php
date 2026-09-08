<?php

namespace Database\Factories;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'codigo' => 'ALM-' . fake()->unique()->numerify('######'),
            'fecha_nacimiento' => fake()->date(),
            'genero' => fake()->randomElement(['M', 'F']),
            'activo' => true,
        ];
    }
}
