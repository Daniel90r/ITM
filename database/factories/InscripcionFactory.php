<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\Alumno;
use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'alumno_id' => AlumnoFactory::new(),
            'curso_id' => CursoFactory::new(),
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
            'documento_path' => null,
            'observacion' => null,
            'motivo_rechazo' => null,
            'tomara_ingles' => false,
        ];
    }
}
