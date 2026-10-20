<?php

namespace Database\Factories;

use App\Models\Nota;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaFactory extends Factory
{
    protected $model = Nota::class;

    public function definition(): array
    {
        return [
            'alumno_id'         => \App\Models\Alumno::factory(),
            'detalle_curso_id'  => \App\Models\DetalleCurso::factory(),
            'laboratorio'       => fake()->randomFloat(2, 0, 10),
            'examen_teorico'    => fake()->randomFloat(2, 0, 10),
            'practica'          => fake()->randomFloat(2, 0, 10),
            'conducta'          => fake()->randomElement(['E', 'MB', 'B', 'R', 'D']),
            'promedio'          => fake()->numberBetween(1, 10),
            'registrado_por'    => \App\Models\Maestro::factory(),
        ];
    }
}