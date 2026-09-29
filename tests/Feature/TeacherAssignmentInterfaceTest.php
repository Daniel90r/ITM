<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearDatosBase(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'MST80001',
            'activo' => true,
        ]);

        $curso = Curso::create([
            'nombre' => 'Test Curso',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        $materia = Materia::create([
            'nombre' => 'Test Materia',
            'codigo' => 'MAT80001',
            'descripcion' => 'Test',
            'activa' => true,
        ]);

        return [
            'admin' => $admin,
            'maestroUser' => $maestroUser,
            'maestro' => $maestro,
            'curso' => $curso,
            'materia' => $materia,
        ];
    }

    public function test_admin_can_open_maestro_detail_page(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertRedirect(route('login'));
    }

    public function test_docente_recibe_403(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['maestroUser'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(403);
    }

    public function test_alumno_recibe_403(): void
    {
        $data = $this->crearDatosBase();
        $alumno = User::factory()->create(['role' => 'alumno']);

        $response = $this->actingAs($alumno)
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(403);
    }

    public function test_detail_shows_only_selected_maestro(): void
    {
        $data = $this->crearDatosBase();

        $otroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Apellido',
            'codigo' => 'MST99999',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['maestro']->nombre, false);
        $response->assertSee($otroMaestro->nombre, false);
    }

    public function test_detail_does_not_show_other_maestro_data(): void
    {
        $data = $this->crearDatosBase();

        $otroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroUser->id,
            'nombre' => 'Confidencial',
            'apellido' => 'Datos',
            'codigo' => 'MST88888',
            'telefono' => '7999-9999',
            'dui' => '87654321-0',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee($otroMaestro->codigo, false);
        $response->assertDontSee($otroMaestro->dui, false);
        $response->assertDontSee($otroMaestro->telefono, false);
        $response->assertDontSee($otroUser->email, false);
        $response->assertDontSee($otroUser->password, false);
    }

    public function test_asignar_form_uses_correct_route(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee(route('admin.maestros.asignar', $data['maestro']->id), false);
    }

    public function test_asignar_form_uses_post_and_csrf(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_select_curso_id_exists(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('name="curso_id"', false);
    }

    public function test_materia_ids_checkboxes_generated_for_selected_curso(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        // Un maestro puede impartir VARIAS materias en el mismo curso: ya no
        // existe el <select name="materia_id"> de selección única.
        $this->assertStringNotContainsString('name="materia_id"', $response->getContent());

        $response->assertSee('id="materiasContainer"', false);
        $response->assertSee('id="materiasList"', false);
        $response->assertSee('Programas / Materias', false);

        // Las materias se pintan en el navegador una vez elegido el curso.
        $js = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString("checkbox.name = 'materia_ids[]'", $js);
        $this->assertStringContainsString('materias.forEach', $js);
        $this->assertStringContainsString("checkbox.type = 'checkbox'", $js);
    }

    public function test_curso_select_required_and_materias_required_on_server(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent(), LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);

        $cursoSelect = $xpath->query('//select[@name="curso_id"]')->item(0);

        $this->assertNotNull($cursoSelect, 'El select curso_id existe en el DOM');
        $this->assertTrue($cursoSelect->hasAttribute('required'), 'El select curso_id tiene el atributo required');

        // La selección múltiple son checkboxes: un `required` por elemento
        // obligaría al administrador a marcarlas todas. La obligatoriedad la
        // garantiza el servidor.
        $this->assertStringNotContainsString('name="materia_id"', $response->getContent());

        $this->actingAs($data['admin'])
            ->from(route('admin.maestros.show', $data['maestro']->id))
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => $data['curso']->id,
            ])
            ->assertSessionHasErrors('materia_ids');
    }

    public function test_materias_ajenas_o_tomadas_quedan_bloqueadas_en_la_lista(): void
    {
        $js = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('materia.tiene_maestro_actual', $js);
        $this->assertStringContainsString('materia.ya_asignada_a_este_maestro', $js);
        $this->assertStringContainsString('checkbox.disabled = bloqueada;', $js);

        // "(Asignada a X)" y "(Ya asignada a este maestro)" deben llegar como
        // texto, nunca inyectados como HTML.
        $this->assertStringContainsString('estado.textContent = estadoTexto;', $js);
        $this->assertStringContainsString('nombre.textContent =', $js);
    }

    private function crearCurso(string $nombre, string $nivel = 'Matutino', string $seccion = 'A'): Curso
    {
        return Curso::create([
            'nombre' => $nombre,
            'nivel' => $nivel,
            'seccion' => $seccion,
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);
    }

    private function crearMateria(string $nombre, string $codigo, bool $activa = true): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'descripcion' => 'Test',
            'activa' => $activa,
        ]);
    }

    private function codigosDeMateriasDelCurso(User $admin, Curso $curso, Maestro $maestro): \Illuminate\Support\Collection
    {
        $response = $this->actingAs($admin)
            ->getJson(route('admin.maestros.materiasPorCurso', [
                'curso_id' => $curso->id,
                'maestro_id' => $maestro->id,
            ]));

        $response->assertOk();

        return collect($response->json('materias'))->pluck('codigo');
    }

    public function test_only_active_cursos_shown(): void
    {
        $data = $this->crearDatosBase();

        Curso::create([
            'nombre' => 'Curso Inactivo',
            'nivel' => 'Vespertino',
            'seccion' => 'B',
            'anio_lectivo' => 2025,
            'activo' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Test Curso', false);
    }

    public function test_inactive_cursos_not_shown(): void
    {
        $data = $this->crearDatosBase();

        Curso::create([
            'nombre' => 'Curso Inactivo XYZ',
            'nivel' => 'Vespertino',
            'seccion' => 'B',
            'anio_lectivo' => 2025,
            'activo' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('Curso Inactivo XYZ', false);
    }

    public function test_only_active_materias_shown(): void
    {
        $data = $this->crearDatosBase();

        // Las materias ya no se imprimen en el HTML del servidor: se piden por
        // AJAX al elegir el curso. La materia activa del curso sí debe ofrecerse.
        $activa = $this->crearMateria('Materia Activa XYZ', 'MAT80002');
        $this->crearMateria('Materia Inactiva', 'MAT99999', false);

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $activa->id,
        ]);

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
        ]);

        $codigos = $this->codigosDeMateriasDelCurso($data['admin'], $data['curso'], $data['maestro']);

        $this->assertTrue($codigos->contains('MAT80001'));
        $this->assertTrue($codigos->contains('MAT80002'));
    }

    public function test_inactive_materias_not_shown(): void
    {
        $data = $this->crearDatosBase();

        $inactiva = $this->crearMateria('Materia Inactiva ABC', 'MAT99999', false);
        $deOtroCurso = $this->crearMateria('Materia De Otro Curso', 'MAT80002');
        $otroCurso = $this->crearCurso('Curso Ajeno', 'Vespertino', 'B');

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $inactiva->id,
        ]);

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
        ]);

        DetalleCurso::create([
            'curso_id' => $otroCurso->id,
            'materia_id' => $deOtroCurso->id,
        ]);

        $codigos = $this->codigosDeMateriasDelCurso($data['admin'], $data['curso'], $data['maestro']);

        $this->assertFalse($codigos->contains('MAT99999'), 'Una materia inactiva no debe ofrecerse.');
        $this->assertFalse($codigos->contains('MAT80002'), 'Una materia de otro curso no debe ofrecerse.');
    }

    public function test_curso_id_old_input_preserved_when_materia_fails(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => $data['curso']->id,
                'materia_ids' => [999999],
            ]);

        $response->assertSee('value="'.$data['curso']->id.'" selected', false);
    }

    public function test_materia_ids_old_input_preserved_when_curso_fails(): void
    {
        $data = $this->crearDatosBase();

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => '',
                'materia_ids' => [$data['materia']->id],
            ]);

        $response->assertSee('data-open="1"', false);

        // El modal debe rememberar cuáles materias estaban marcadas para que el
        // JS las vuelva a marcar y el administrador no repita la selección.
        $this->assertMatchesRegularExpression(
            '/data-materias-seleccionadas="[^"]*'.$data['materia']->id.'[^"]*"/',
            $response->getContent()
        );

        $js = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('modal.dataset.materiasSeleccionadas', $js);
        $this->assertStringContainsString('checkbox.checked = !bloqueada && seleccionadas.includes(String(materia.id));', $js);
    }

    public function test_validation_errors_reopen_modal(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => 999999,
                'materia_ids' => [999999],
            ]);

        $response->assertSee('data-open="1"', false);
    }

    public function test_active_maestro_shows_asignar_programa_button(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('abrirModalAsignarCurso()', false);
        $response->assertSee('Asignar programa', false);
    }

    public function test_inactive_maestro_does_not_show_asignar_button(): void
    {
        $data = $this->crearDatosBase();
        $data['maestro']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalAsignarCurso()', false);
    }

    public function test_existing_assignment_shows_curso_nivel_materia_codigo(): void
    {
        $data = $this->crearDatosBase();

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['curso']->nombre, false);
        $response->assertSee($data['curso']->nivel, false);
        $response->assertSee($data['curso']->seccion, false);
        $response->assertSee($data['materia']->nombre, false);
        $response->assertSee($data['materia']->codigo, false);
    }

    public function test_no_assignments_shows_empty_state(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Este maestro no tiene programas asignados.', false);
    }

    public function test_desasignar_form_has_correct_route(): void
    {
        $data = $this->crearDatosBase();

        $detalle = DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee(route('admin.maestros.desasignar', [$data['maestro']->id, $detalle->id]), false);
        $response->assertSee('name="_method"', false);
        $response->assertSee('value="DELETE"', false);
    }

    public function test_curso_and_materia_eager_loaded(): void
    {
        $data = $this->crearDatosBase();

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $maestroVista = $response->viewData('maestro');

        $this->assertTrue($maestroVista->relationLoaded('detalleCursos'));

        $detalleVista = $maestroVista->detalleCursos->first();
        $this->assertTrue($detalleVista->relationLoaded('curso'));
        $this->assertTrue($detalleVista->relationLoaded('materia'));
    }

    public function test_modal_included_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-asignar-curso['\"]\s*\)/", $content));
    }

    public function test_maestros_js_loaded_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@vite\s*\(\s*['\"]resources\/js\/admin\/maestros\.js['\"]\s*\)/", $content));
    }

    public function test_no_obsolete_js_url_concatenation(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $this->assertStringNotContainsString("/admin/maestros/' + maestroId", $html);
    }

    public function test_modals_js_exposes_new_functions(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringContainsString('window.abrirModalAsignarCurso = abrirModalAsignarCurso', $content);
        $this->assertStringContainsString('window.cerrarModalAsignarCurso = cerrarModalAsignarCurso', $content);
    }

    public function test_modals_js_no_obsolete_signature(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringNotContainsString('abrirModalAsignar(maestroId, maestroNombre)', $content);
        $this->assertStringNotContainsString("'/admin/maestros/' + maestroId + '/asignar'", $content);
    }

    public function test_modals_js_null_safe_listeners(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('if (modalNuevo', $content);
        $this->assertStringContainsString('if (modalEditar', $content);
        $this->assertStringContainsString('if (modalAsignar', $content);
        $this->assertStringContainsString('modalAsignar.dataset', $content);
        $this->assertStringContainsString('modalAsignarAbierto', $content);
    }
}
