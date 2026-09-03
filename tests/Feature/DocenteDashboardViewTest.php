<?php

namespace Tests\Feature;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocenteDashboardViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearDocente(string $codigo = 'MST00001', string $nombre = 'Test', string $apellido = 'Docente'): Maestro
    {
        $user = User::factory()->create([
            'name' => $nombre.' '.$apellido,
            'role' => 'docente',
        ]);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'codigo' => $codigo,
            'activo' => true,
        ]);
    }

    private function crearCurso(
        string $seccion = 'A',
        string $nombre = 'Primer Grado',
        string $nivel = 'Básica',
        int $anioLectivo = 2025,
        bool $activo = true,
    ): Curso {
        return Curso::create([
            'nombre' => $nombre,
            'nivel' => $nivel,
            'seccion' => $seccion,
            'anio_lectivo' => $anioLectivo,
            'activo' => $activo,
        ]);
    }

    private function crearMateria(string $nombre = 'Materia Test', string $codigo = 'MAT00001', bool $activa = true): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'descripcion' => 'Test',
            'activa' => $activa,
        ]);
    }

    private function crearDetalle(Maestro $maestro, Curso $curso, Materia $materia): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestro->id,
        ]);
    }

    private function crearAsignacion(
        DetalleCurso $detalle,
        Maestro $maestro,
        string $estado = 'activa',
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
    ): AsignacionDocente {
        return AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado' => $estado,
            'motivo_cambio' => null,
            'asignado_por' => null,
        ]);
    }

    private function usuarioDocente(Maestro $maestro): User
    {
        return User::find($maestro->user_id);
    }

    public function test_panel_muestra_nombre_y_codigo_del_docente(): void
    {
        $docente = $this->crearDocente('MST00001', 'Juan', 'Pérez');
        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Juan Pérez', false);
        $response->assertSee('Código: MST00001', false);
    }

    public function test_muestra_cantidad_correcta_de_cursos(): void
    {
        $docente = $this->crearDocente();

        $curso1 = $this->crearCurso('A', 'Curso Uno', 'Básica');
        $materia1 = $this->crearMateria('Materia Uno', 'MAT00001');
        $detalle1 = $this->crearDetalle($docente, $curso1, $materia1);
        $this->crearAsignacion($detalle1, $docente, 'activa');

        $curso2 = $this->crearCurso('B', 'Curso Dos', 'Media');
        $materia2 = $this->crearMateria('Materia Dos', 'MAT00002');
        $detalle2 = $this->crearDetalle($docente, $curso2, $materia2);
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('2 cursos', false);
    }

    public function test_muestra_cantidad_total_correcta_de_programas(): void
    {
        $docente = $this->crearDocente();

        $curso1 = $this->crearCurso('A', 'Curso Uno', 'Básica');
        $materia1 = $this->crearMateria('Materia A', 'MAT00001');
        $detalle1 = $this->crearDetalle($docente, $curso1, $materia1);
        $this->crearAsignacion($detalle1, $docente, 'activa');

        $curso2 = $this->crearCurso('B', 'Curso Dos', 'Media');
        $materia2 = $this->crearMateria('Materia B', 'MAT00002');
        $detalle2 = $this->crearDetalle($docente, $curso2, $materia2);
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('2 programas', false);
    }

    public function test_maneja_correctamente_singular_y_plural(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Singular', 'Básica');
        $materia = $this->crearMateria('Materia Singular', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('1 curso', false);
        $response->assertDontSee('1 cursos', false);
        $response->assertSee('1 programa', false);
        $response->assertDontSee('1 programas', false);
    }

    public function test_tarjeta_muestra_nombre_nivel_seccion_y_anio_lectivo(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('C', 'Décimo Primer Grado', 'Media', 2025);
        $materia = $this->crearMateria('Matemáticas', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Décimo Primer Grado', false);
        $response->assertSee('Media', false);
        $response->assertSee('Sección C', false);
        $response->assertSee('Año 2025', false);
    }

    public function test_tarjeta_muestra_distintivo_activo(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Activa', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Activo', false);
    }

    public function test_tarjeta_muestra_nombre_y_codigo_de_materia(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Lenguaje y Comunicación', 'MAT-LC-001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Lenguaje y Comunicación', false);
        $response->assertSee('MAT-LC-001', false);
    }

    public function test_dos_programas_mismo_curso_dentro_de_una_tarjeta(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Doble', 'Básica');
        $materia1 = $this->crearMateria('Programa Uno', 'MAT00001');
        $materia2 = $this->crearMateria('Programa Dos', 'MAT00002');
        $detalle1 = $this->crearDetalle($docente, $curso, $materia1);
        $detalle2 = $this->crearDetalle($docente, $curso, $materia2);
        $this->crearAsignacion($detalle1, $docente, 'activa');
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Programa Uno', false);
        $response->assertSee('Programa Dos', false);

        $html = $response->getContent();
        $dataCourseIdCount = substr_count($html, 'data-course-id="'.$curso->id.'"');
        $this->assertEquals(1, $dataCourseIdCount, 'El data-course-id debe aparecer exactamente una vez para este curso.');
    }

    public function test_data_course_id_aparece_exactamente_una_vez_por_curso(): void
    {
        $docente = $this->crearDocente();

        $curso1 = $this->crearCurso('A', 'Curso A', 'Básica');
        $materia1 = $this->crearMateria('Materia A', 'MAT00001');
        $detalle1 = $this->crearDetalle($docente, $curso1, $materia1);
        $this->crearAsignacion($detalle1, $docente, 'activa');

        $curso2 = $this->crearCurso('B', 'Curso B', 'Media');
        $materia2 = $this->crearMateria('Materia B', 'MAT00002');
        $detalle2 = $this->crearDetalle($docente, $curso2, $materia2);
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();

        $html = $response->getContent();
        $count1 = substr_count($html, 'data-course-id="'.$curso1->id.'"');
        $count2 = substr_count($html, 'data-course-id="'.$curso2->id.'"');
        $this->assertEquals(1, $count1, 'Curso 1 debe tener su data-course-id una sola vez.');
        $this->assertEquals(1, $count2, 'Curso 2 debe tener su data-course-id una sola vez.');
    }

    public function test_dos_cursos_generan_dos_tarjetas(): void
    {
        $docente = $this->crearDocente();

        $curso1 = $this->crearCurso('A', 'Curso Primero', 'Básica');
        $materia1 = $this->crearMateria('Materia P', 'MAT00001');
        $detalle1 = $this->crearDetalle($docente, $curso1, $materia1);
        $this->crearAsignacion($detalle1, $docente, 'activa');

        $curso2 = $this->crearCurso('B', 'Curso Segundo', 'Media');
        $materia2 = $this->crearMateria('Materia S', 'MAT00002');
        $detalle2 = $this->crearDetalle($docente, $curso2, $materia2);
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Curso Primero', false);
        $response->assertSee('Curso Segundo', false);

        $html = $response->getContent();
        $cardsCount = substr_count($html, 'data-course-id=');
        $this->assertEquals(2, $cardsCount, 'Debe haber exactamente 2 tarjetas.');
    }

    public function test_cada_programa_tiene_data_detail_id_correcto(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Test', 'Básica');
        $materia = $this->crearMateria('Materia Test', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('data-detail-id="'.$detalle->id.'"', false);
    }

    public function test_boton_registrar_notas_usa_ruta_correcta(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Ruta', 'Básica');
        $materia = $this->crearMateria('Materia Ruta', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee(route('docente.notas', ['detalle_curso_id' => $detalle->id]), false);
        $response->assertSee('Registrar notas', false);
    }

    public function test_estado_vacio_muestra_mensaje_acordado(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('No tienes programas activos asignados actualmente.', false);
    }

    public function test_estado_vacio_no_muestra_registrar_notas(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Registrar notas', false);
    }

    public function test_no_muestra_programas_de_otro_docente(): void
    {
        $docente = $this->crearDocente('MST00001');
        $otroMaestro = $this->crearDocente('MST00002');

        $curso = $this->crearCurso('A', 'Curso Ajeno', 'Básica');
        $materia = $this->crearMateria('Materia Ajena', 'MAT00001');
        $detalle = $this->crearDetalle($otroMaestro, $curso, $materia);
        $this->crearAsignacion($detalle, $otroMaestro, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Curso Ajeno', false);
        $response->assertDontSee('Materia Ajena', false);
        $response->assertSee('No tienes programas activos asignados actualmente.', false);
    }

    public function test_no_muestra_asignaciones_finalizadas(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Finalizado', 'Básica');
        $materia = $this->crearMateria('Materia Finalizada', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'finalizada');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Curso Finalizado', false);
        $response->assertDontSee('Materia Finalizada', false);
    }

    public function test_no_muestra_cursos_inactivos(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Inactivo', 'Básica', 2025, false);
        $materia = $this->crearMateria('Materia Inactiva', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Curso Inactivo', false);
        $response->assertDontSee('Materia Inactiva', false);
    }

    public function test_no_muestra_materias_inactivas(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Con Materia Inactiva', 'Básica');
        $materia = $this->crearMateria('Materia Inactiva', 'MAT00001', false);
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Inactiva', false);
    }

    public function test_nombres_de_curso_y_programa_se_muestran_escapados(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', '<b>Curso XSS</b>', 'Básica');
        $materia = $this->crearMateria('<script>alert(1)</script>', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('&lt;b&gt;Curso XSS&lt;/b&gt;', false);
        $response->assertDontSee('<b>Curso XSS</b>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_programa_no_contiene_enlace_directo_de_asistencia(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A', 'Curso Test', 'Básica');
        $materia = $this->crearMateria('Materia Test', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();

        $html = $response->getContent();
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(sprintf('<?xml encoding="utf-8" ?>%s', $html), LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        $detailNodes = $xpath->query(sprintf('//*[@data-detail-id="%s"]', $detalle->id));
        $this->assertGreaterThan(0, $detailNodes->length, 'Debe existir el contenedor del programa.');

        $asistenciaCount = 0;
        foreach ($detailNodes as $node) {
            foreach ($node->getElementsByTagName('a') as $link) {
                if (strpos($link->getAttribute('href'), route('docente.asistencia')) !== false) {
                    $asistenciaCount++;
                }
            }
        }

        $this->assertEquals(0, $asistenciaCount, 'El programa no debe contener un enlace directo de asistencia.');
    }

    public function test_sidebar_conserva_enlace_general_de_asistencia(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee(route('docente.asistencia'), false);
        $response->assertSee('Asistencia', false);
    }

    public function test_respuesta_200_para_docente_sin_asignaciones(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertStatus(200);
    }

    public function test_no_se_ejecutan_consultas_eloquent_desde_la_vista(): void
    {
        $blade = file_get_contents(resource_path('views/docente/dashboard.blade.php'));

        $this->assertStringNotContainsString('::query(', $blade);
        $this->assertStringNotContainsString('::where(', $blade);
        $this->assertStringNotContainsString('::find(', $blade);
        $this->assertStringNotContainsString('::latest(', $blade);
        $this->assertStringNotContainsString('::orderBy(', $blade);
        $this->assertStringNotContainsString('::whereHas(', $blade);
        $this->assertStringNotContainsString('::all(', $blade);
        $this->assertStringNotContainsString('::get(', $blade);

        $this->assertDoesNotMatchRegularExpression('/->get\(\)/', $blade);
        $this->assertStringNotContainsString('->find(', $blade);
        $this->assertStringNotContainsString('->where(', $blade);
    }
}
