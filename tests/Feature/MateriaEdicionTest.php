<?php

namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Asistencia, DetalleCurso, Materia, Nota, User};
use App\Rules\NombreMateriaUnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MateriaEdicionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_creacion_normaliza_y_guarda_los_campos_del_formulario(): void
    {
        $this->post(route('admin.materias.store'), ['nombre' => "  Diseño\t  Gráfico  ", 'descripcion' => 'Descripción', 'activa' => '0'])
            ->assertRedirect(route('admin.materias'))->assertSessionHas('success');
        $this->assertDatabaseHas('materias', ['nombre' => 'Diseño Gráfico', 'descripcion' => 'Descripción', 'activa' => 0]);
    }

    public function test_rechaza_duplicados_normalizados_incluso_inactivos(): void
    {
        Materia::factory()->create(['nombre' => '  Matemáticas   Aplicadas ', 'activa' => false]);
        foreach (['Matemáticas Aplicadas', 'MATEMÁTICAS APLICADAS', " matematicas\t aplicadas ", "Matemáticas\u{00A0}Aplicadas"] as $nombre) {
            $this->postJson(route('admin.materias.store'), ['nombre' => $nombre])->assertUnprocessable()
                ->assertJsonValidationErrors(['nombre' => NombreMateriaUnico::MENSAJE]);
        }
        $this->assertDatabaseCount('materias', 1);
    }

    public function test_editar_nombre_preserva_codigo_descripcion_estado_y_opcional(): void
    {
        $m = Materia::factory()->create(['nombre' => 'Inglés', 'codigo' => 'ING', 'descripcion' => 'Descripción previa', 'activa' => false, 'es_opcional' => true]);
        $this->get(route('admin.materias.edit', $m))->assertOk()->assertSee('Inglés')->assertSee('Opcional')->assertSee('ING');
        $this->put(route('admin.materias.update', $m), ['nombre' => '  Inglés   Avanzado ', 'codigo' => 'OTRO', 'es_opcional' => false, 'activa' => true])
            ->assertRedirect(route('admin.materias'))->assertSessionHas('success');
        $this->assertDatabaseHas('materias', ['id' => $m->id, 'nombre' => 'Inglés Avanzado', 'codigo' => 'ING', 'descripcion' => 'Descripción previa', 'activa' => 0, 'es_opcional' => 1]);
        $this->put(route('admin.materias.update', $m), ['nombre' => 'Inglés Avanzado', 'descripcion' => 'Actualizada'])->assertSessionHas('success');
        $this->assertSame('Actualizada', $m->fresh()->descripcion);
    }

    public function test_permite_mismo_nombre_y_rechaza_nombre_de_otra_materia(): void
    {
        $m = Materia::factory()->create(['nombre' => 'Microsoft Word']);
        $otro = Materia::factory()->create(['nombre' => 'Microsoft Windows']);
        $this->put(route('admin.materias.update', $m), ['nombre' => 'Microsoft Word'])->assertSessionHas('success');
        $this->putJson(route('admin.materias.update', $m), ['nombre' => ' MICROSOFT  WINDOWS '])->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => NombreMateriaUnico::MENSAJE]);
        $this->assertSame('Microsoft Word', $m->fresh()->nombre);
        $this->assertSame('Microsoft Windows', $otro->fresh()->nombre);
    }

    public function test_nombre_obligatorio_y_errores_conservan_la_entrada(): void
    {
        $m = Materia::factory()->create();
        foreach (['', '   ', str_repeat('a', 101), ['inválido']] as $nombre) {
            $this->putJson(route('admin.materias.update', $m), ['nombre' => $nombre])->assertUnprocessable()->assertJsonValidationErrors('nombre');
            $this->postJson(route('admin.materias.store'), ['nombre' => $nombre])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        }
    }

    public function test_errores_de_edicion_se_muestran_con_el_nombre_introducido(): void
    {
        $m = Materia::factory()->create();
        Materia::factory()->create(['nombre' => 'Duplicada']);
        $this->from(route('admin.materias.edit', $m))->put(route('admin.materias.update', $m), ['nombre' => 'Duplicada'])
            ->assertRedirect(route('admin.materias.edit', $m))->assertSessionHasErrors('nombre')->assertSessionHasInput('nombre', 'Duplicada');
        $this->withViewErrors(['nombre' => NombreMateriaUnico::MENSAJE])
            ->view('admin.materia-editar', ['materia' => $m])->assertSee(NombreMateriaUnico::MENSAJE);
    }

    public function test_no_confunde_palabras_ni_niveles_distintos_y_no_fusiona_duplicados(): void
    {
        Materia::factory()->create(['nombre' => 'Base de Datos']);
        foreach (['Base Datos', 'Base de Datos II', 'BasedeDatos'] as $nombre) {
            $this->post(route('admin.materias.store'), ['nombre' => $nombre])->assertSessionHasNoErrors();
        }
        $a = Materia::factory()->create(['nombre' => 'Duplicado previo']);
        Materia::factory()->create(['nombre' => ' DUPLICADO  PREVIO ']);
        $this->putJson(route('admin.materias.update', $a), ['nombre' => $a->nombre])->assertUnprocessable();
        $this->assertDatabaseCount('materias', 6);
    }

    public function test_admin_exclusivo_y_registro_inexistente(): void
    {
        $m = Materia::factory()->create();
        $this->get(route('admin.materias.edit', 999999))->assertNotFound();
        foreach (['alumno', 'docente'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.materias.edit', $m))->assertForbidden();
            $this->put(route('admin.materias.update', $m), ['nombre' => 'Modificada'])->assertForbidden();
            $this->post(route('admin.materias.store'), ['nombre' => 'Modificada'])->assertForbidden();
        }
        auth()->logout(); $this->get(route('admin.materias.edit', $m))->assertRedirect(route('login'));
    }

    public function test_edicion_no_cambia_relaciones_academicas(): void
    {
        $m = Materia::factory()->create(['nombre' => 'Inglés', 'es_opcional' => true]);
        $a = Alumno::factory()->create();
        foreach (range(1, 2) as $_) {
            $dc = DetalleCurso::factory()->create(['materia_id' => $m->id]);
            AsignacionDocente::create(['detalle_curso_id' => $dc->id, 'maestro_id' => $dc->maestro_id, 'estado' => 'activa']);
            Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $dc->id]);
            Asistencia::create(['alumno_id' => $a->id, 'curso_id' => $dc->curso_id, 'detalle_curso_id' => $dc->id, 'registrado_por' => $dc->maestro_id, 'fecha' => '2026-10-03', 'estado' => 'presente']);
            DB::table('alumno_materia')->insert(['alumno_id' => $a->id, 'materia_id' => $m->id, 'curso_id' => $dc->curso_id]);
        }
        $tablas = ['detalle_cursos', 'cursos', 'maestros', 'asignaciones_docentes', 'notas', 'asistencias', 'alumno_materia'];
        $antes = []; foreach ($tablas as $t) $antes[$t] = DB::table($t)->get()->toJson();
        $this->put(route('admin.materias.update', $m), ['nombre' => 'Inglés actualizado'])->assertSessionHas('success');
        foreach ($tablas as $t) $this->assertSame($antes[$t], DB::table($t)->get()->toJson(), $t);
        $this->assertSame(2, $m->detalleCursos()->count());
    }
}
