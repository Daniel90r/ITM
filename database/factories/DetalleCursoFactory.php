<?php

namespace Database\Factories;

use App\Models\DetalleCurso;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleCursoFactory extends Factory
{
    protected $model = DetalleCurso::class;

    public function definition(): array
    {
        return [
            'curso_id' => \App\Models\Curso::factory(),
            'materia_id' => \App\Models\Materia::factory(),
            'maestro_id' => \App\Models\Maestro::factory(),
        ];
    }
}