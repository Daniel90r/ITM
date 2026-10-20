<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\DetalleBoleta;
use App\Models\DetalleCurso;
use App\Models\Director;
use App\Models\Inscripcion;
use App\Models\Materia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoletaHistoricalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_boletas_table_exists(): void
    {
        $this->assertTrue(
            \Schema::hasTable('boletas'),
            'La tabla boletas debe existir.'
        );
    }

    public function test_detalles_boletas_table_exists(): void
    {
        $this->assertTrue(
            \Schema::hasTable('detalles_boletas'),
            'La tabla detalles_boletas debe existir.'
        );
    }

    public function test_boletas_columns_exist(): void
    {
        $columns = [
            'id',
            'alumno_id',
            'curso_id',
            'director_id',
            'inscripcion_id',
            'fecha_emision',
            'alumno_nombre',
            'alumno_apellido',
            'alumno_codigo',
            'curso_nombre',
            'curso_turno',
            'curso_seccion',
            'curso_anio_lectivo',
            'director_nombre',
            'director_firma_snapshot_path',
            'promedio_general',
            'concepto_general',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                \Schema::hasColumn('boletas', $column),
                "La columna {$column} debe existir en boletas."
            );
        }
    }

    public function test_detalles_boletas_columns_exist(): void
    {
        $columns = [
            'id',
            'boleta_id',
            'detalle_curso_id',
            'materia_id',
            'materia_nombre',
            'materia_codigo',
            'laboratorio',
            'examen_teorico',
            'practica',
            'conducta',
            'promedio',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                \Schema::hasColumn('detalles_boletas', $column),
                "La columna {$column} debe existir en detalles_boletas."
            );
        }
    }

    public function test_boletas_column_types(): void
    {
        $cols = \Schema::getColumnListing('boletas');
        $this->assertContains('alumno_id', $cols);
        $this->assertContains('curso_id', $cols);
        $this->assertContains('director_id', $cols);
        $this->assertContains('inscripcion_id', $cols);
    }

    public function test_detalles_boletas_column_types(): void
    {
        $cols = \Schema::getColumnListing('detalles_boletas');
        $this->assertContains('boleta_id', $cols);
        $this->assertContains('detalle_curso_id', $cols);
        $this->assertContains('materia_id', $cols);
    }

    public function test_models_can_persist(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $director = Director::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id]);

        $boleta = Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);

        $this->assertNotNull($boleta->id);
        $this->assertEquals($alumno->id, $boleta->alumno_id);
        $this->assertEquals($curso->id, $boleta->curso_id);
        $this->assertEquals($director->id, $boleta->director_id);
        $this->assertEquals($inscripcion->id, $boleta->inscripcion_id);
    }

    public function test_boleta_relationships(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $director = Director::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id]);

        $boleta = Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);

        $this->assertInstanceOf(Alumno::class, $boleta->alumno);
        $this->assertEquals($alumno->id, $boleta->alumno->id);

        $this->assertInstanceOf(Curso::class, $boleta->curso);
        $this->assertEquals($curso->id, $boleta->curso->id);

        $this->assertInstanceOf(Director::class, $boleta->director);
        $this->assertEquals($director->id, $boleta->director->id);

        $this->assertInstanceOf(Inscripcion::class, $boleta->inscripcion);
        $this->assertEquals($inscripcion->id, $boleta->inscripcion->id);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $boleta->detalles);
    }

    public function test_detalle_boleta_relationships(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $director = Director::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id]);

        $boleta = Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);

        $materia = Materia::factory()->create();
        $detalleCurso = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        $detalle = DetalleBoleta::create([
            'boleta_id' => $boleta->id,
            'detalle_curso_id' => $detalleCurso->id,
            'materia_id' => $materia->id,
            'materia_nombre' => $materia->nombre,
            'materia_codigo' => $materia->codigo,
            'laboratorio' => 8.50,
            'examen_teorico' => 7.75,
            'practica' => 8.00,
            'conducta' => 'B',
            'promedio' => 8,
        ]);

        $this->assertInstanceOf(Boleta::class, $detalle->boleta);
        $this->assertEquals($boleta->id, $detalle->boleta->id);

        $this->assertInstanceOf(DetalleCurso::class, $detalle->detalleCurso);
        $this->assertEquals($detalleCurso->id, $detalle->detalleCurso->id);

        $this->assertInstanceOf(Materia::class, $detalle->materia);
        $this->assertEquals($materia->id, $detalle->materia->id);
    }

    public function test_unique_alumno_curso(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $director = Director::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id]);

        Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);
    }

    public function test_unique_boleta_detalle_curso(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $director = Director::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id]);

        $boleta = Boleta::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'director_id' => $director->id,
            'inscripcion_id' => $inscripcion->id,
            'fecha_emision' => now(),
            'alumno_nombre' => $alumno->nombre,
            'alumno_apellido' => $alumno->apellido,
            'alumno_codigo' => $alumno->codigo,
            'curso_nombre' => $curso->nombre,
            'curso_turno' => $curso->nivel,
            'curso_seccion' => $curso->seccion,
            'curso_anio_lectivo' => $curso->anio_lectivo,
            'director_nombre' => $director->nombre,
            'director_firma_snapshot_path' => 'firmas/test.png',
            'promedio_general' => 8,
            'concepto_general' => 'AP',
        ]);

        $materia = Materia::factory()->create();
        $detalleCurso = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        DetalleBoleta::create([
            'boleta_id' => $boleta->id,
            'detalle_curso_id' => $detalleCurso->id,
            'materia_id' => $materia->id,
            'materia_nombre' => $materia->nombre,
            'materia_codigo' => $materia->codigo,
            'laboratorio' => 8.50,
            'examen_teorico' => 7.75,
            'practica' => 8.00,
            'conducta' => 'B',
            'promedio' => 8,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DetalleBoleta::create([
            'boleta_id' => $boleta->id,
            'detalle_curso_id' => $detalleCurso->id,
            'materia_id' => $materia->id,
            'materia_nombre' => $materia->nombre,
            'materia_codigo' => $materia->codigo,
            'laboratorio' => 7.00,
            'examen_teorico' => 6.50,
            'practica' => 7.25,
            'conducta' => 'A',
            'promedio' => 7,
        ]);
    }
}