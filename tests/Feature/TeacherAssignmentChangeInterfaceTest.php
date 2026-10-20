<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentChangeInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearEscenarioBasico(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre' => 'Principal',
            'apellido' => 'Actual',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'MST00001',
            'activo' => true,
        ]);

        $alternativoUser = User::factory()->create(['role' => 'docente']);
        $maestroAlternativo = Maestro::create([
            'user_id' => $alternativoUser->id,
            'nombre' => 'Alternativo',
            'apellido' => 'Sustituto',
            'telefono' => '7999-9999',
            'dui' => '87654321-0',
            'codigo' => 'MST00002',
            'activo' => true,
        ]);

        $curso = Curso::create([
            'nombre' => '1er Grado',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        $materia = Materia::create([
            'nombre' => 'Materia Regular C',
            'codigo' => 'MAT00001',
            'descripcion' => 'Basica',
            'activa' => true,
        ]);

        $detalle = DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestro->id,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'estado' => 'activa',
            'motivo_cambio' => null,
            'asignado_por' => $admin->id,
        ]);

        return [
            'admin' => $admin,
            'maestro' => $maestro,
            'maestroUser' => $maestroUser,
            'maestroAlternativo' => $maestroAlternativo,
            'alternativoUser' => $alternativoUser,
            'curso' => $curso,
            'materia' => $materia,
            'detalle' => $detalle,
        ];
    }

    private function crearEscenarioConHistorial(): array
    {
        $data = $this->crearEscenarioBasico();

        $exMaestroUser = User::factory()->create(['role' => 'docente']);
        $exMaestro = Maestro::create([
            'user_id' => $exMaestroUser->id,
            'nombre' => 'ExMaestro',
            'apellido' => 'Anterior',
            'codigo' => 'MST00010',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $exMaestro->id,
            'fecha_inicio' => '2025-03-15',
            'fecha_fin' => '2025-06-14',
            'estado' => 'finalizada',
            'motivo_cambio' => 'Traslado de área',
            'asignado_por' => $data['admin']->id,
        ]);

        $data['exMaestro'] = $exMaestro;
        $data['exMaestroUser'] = $exMaestroUser;

        return $data;
    }

    public function test_shows_cambiar_maestro_button_when_conditions_met(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('abrirModalCambiarMaestro(', false);
    }

    public function test_button_hidden_when_no_active_assignment(): void
    {
        $data = $this->crearEscenarioBasico();

        AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)->delete();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalCambiarMaestro(', false);
        $response->assertSee('Historial inconsistente', false);
    }

    public function test_button_hidden_when_multiple_active_assignments(): void
    {
        $data = $this->crearEscenarioBasico();

        $otroMaestro = Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Otro',
            'apellido' => 'Mas',
            'codigo' => 'MST00003',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $otroMaestro->id,
            'estado' => 'activa',
            'asignado_por' => $data['admin']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalCambiarMaestro(', false);
        $response->assertSee('Historial inconsistente', false);
    }

    public function test_button_hidden_when_active_assignment_belongs_to_other_maestro(): void
    {
        $data = $this->crearEscenarioBasico();

        AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)->delete();

        $otroMaestro = Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Otro',
            'apellido' => 'Mas',
            'codigo' => 'MST00003',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $otroMaestro->id,
            'estado' => 'activa',
            'asignado_por' => $data['admin']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalCambiarMaestro(', false);
        $response->assertSee('Historial inconsistente', false);
    }

    public function test_button_hidden_when_no_alternative_maestros(): void
    {
        $data = $this->crearEscenarioBasico();

        $data['maestroAlternativo']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalCambiarMaestro(', false);
        $response->assertSee('No hay otro maestro activo disponible.', false);
    }

    public function test_inactive_maestros_excluded_from_select(): void
    {
        $data = $this->crearEscenarioBasico();

        $inactiveUser = User::factory()->create(['role' => 'docente']);
        Maestro::create([
            'user_id' => $inactiveUser->id,
            'nombre' => 'InactivoTest',
            'apellido' => 'Exclusivo',
            'codigo' => 'MST00003',
            'activo' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('InactivoTest', false);
        $response->assertDontSee($inactiveUser->email, false);
    }

    public function test_current_maestro_excluded_from_select(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee(
            '<option value="'.$data['maestro']->id.'">',
            false
        );
    }

    public function test_active_alternatives_shown_in_select(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['maestroAlternativo']->nombre_completo, false);
        $response->assertSee(
            'value="'.$data['maestroAlternativo']->id.'"',
            false
        );
    }

    public function test_no_personal_data_of_alternatives_in_html(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee($data['maestroAlternativo']->dui, false);
        $response->assertDontSee($data['maestroAlternativo']->telefono, false);
        $response->assertDontSee($data['alternativoUser']->email, false);
        $response->assertDontSee($data['alternativoUser']->password, false);
    }

    public function test_modal_form_has_csrf_token(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('name="_token"', false);
    }

    public function test_modal_form_has_put_method_spoofing(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringContainsString("@method('PUT')", $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('value="PUT"', false);
    }

    public function test_nuevo_maestro_id_select_required(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringContainsString('name="nuevo_maestro_id"', $content);
        $this->assertStringContainsString('required', $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//select[@name="nuevo_maestro_id"]')->item(0);

        $this->assertNotNull($select, 'El select nuevo_maestro_id existe.');
        $this->assertTrue($select->hasAttribute('required'), 'El select tiene required.');
    }

    public function test_motivo_cambio_textarea_required(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringContainsString('name="motivo_cambio"', $content);
        $this->assertStringContainsString('required', $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $textarea = $xpath->query('//textarea[@name="motivo_cambio"]')->item(0);

        $this->assertNotNull($textarea, 'El textarea motivo_cambio existe.');
        $this->assertTrue($textarea->hasAttribute('required'));
    }

    public function test_motivo_cambio_has_minlength_10(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringContainsString('minlength="10"', $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $textarea = $xpath->query('//textarea[@name="motivo_cambio"]')->item(0);

        $this->assertNotNull($textarea);
        $this->assertTrue($textarea->hasAttribute('minlength'));
        $this->assertEquals('10', $textarea->getAttribute('minlength'));
    }

    public function test_motivo_cambio_has_maxlength_255(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringContainsString('maxlength="255"', $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $textarea = $xpath->query('//textarea[@name="motivo_cambio"]')->item(0);

        $this->assertNotNull($textarea);
        $this->assertTrue($textarea->hasAttribute('maxlength'));
        $this->assertEquals('255', $textarea->getAttribute('maxlength'));
    }

    public function test_cambiar_maestro_route_in_button_onclick(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $rutaEsperada = route('admin.maestros.asignaciones.cambiar', [
            'maestro_id' => $data['maestro']->id,
            'detalle_id' => $data['detalle']->id,
        ]);

        $rutaEscapada = str_replace('/', '\/', $rutaEsperada);

        $response->assertSee($rutaEscapada, false);
    }

    public function test_button_onclick_uses_js_escaped_values(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));

        $this->assertStringContainsString('abrirModalCambiarMaestro(@js(route(', $content);
        $this->assertStringContainsString('@js($detalle->id)', $content);
        $this->assertStringContainsString('@js($detalle->curso', $content);
        $this->assertStringContainsString('@js($detalle->materia', $content);
    }

    public function test_modal_cambiar_maestro_included_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));

        $this->assertSame(
            1,
            preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-cambiar-maestro['\"]\s*\)/", $content)
        );
    }

    public function test_validation_errors_reopen_cambiar_maestro_modal(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => 999999,
                'motivo_cambio' => 'cambio',
                'detalle_id_contexto' => $data['detalle']->id,
            ]);

        $response->assertSee('data-open="1"', false);
    }

    public function test_old_nuevo_maestro_id_preserved_in_select(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => $data['maestroAlternativo']->id,
                'motivo_cambio' => 'cambio12',
                'detalle_id_contexto' => $data['detalle']->id,
            ]);

        $response->assertSee('value="'.$data['maestroAlternativo']->id.'" selected', false);
    }

    public function test_old_motivo_cambio_preserved_in_textarea(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => $data['maestroAlternativo']->id,
                'motivo_cambio' => 'cambio12',
                'detalle_id_contexto' => $data['detalle']->id,
            ]);

        $response->assertSee('cambio12', false);
    }

    public function test_old_detalle_id_contexto_preserved_in_hidden(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => $data['maestroAlternativo']->id,
                'motivo_cambio' => 'cambio12',
                'detalle_id_contexto' => $data['detalle']->id,
            ]);

        $html = $response->getContent();
        $this->assertStringContainsString(
            'name="detalle_id_contexto"',
            $html
        );
        $this->assertStringContainsString(
            'value="'.$data['detalle']->id.'"',
            $html
        );
    }

    public function test_wrong_detalle_id_contexto_does_not_generate_action(): void
    {
        $data = $this->crearEscenarioBasico();

        $otroCurso = Curso::create([
            'nombre' => 'Otro Curso',
            'nivel' => 'Vespertino',
            'seccion' => 'B',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        $otraMateria = Materia::create([
            'nombre' => 'Otra Materia',
            'codigo' => 'MATOTRA',
            'descripcion' => 'Otra',
            'activa' => true,
        ]);

        $otroMaestro = Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST00003',
            'activo' => true,
        ]);

        $otroDetalle = DetalleCurso::create([
            'curso_id' => $otroCurso->id,
            'materia_id' => $otraMateria->id,
            'maestro_id' => $otroMaestro->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => 999999,
                'motivo_cambio' => 'cambio',
                'detalle_id_contexto' => $otroDetalle->id,
            ]);

        $rutaIncorrecta = route('admin.maestros.asignaciones.cambiar', [
            'maestro_id' => $data['maestro']->id,
            'detalle_id' => $otroDetalle->id,
        ]);

        $response->assertSee('data-open="0"', false);
        $response->assertDontSee($rutaIncorrecta, false);
        $response->assertSee('data-action=""', false);
    }

    public function test_valid_contexto_reopens_modal_with_correct_action(): void
    {
        $data = $this->crearEscenarioBasico();

        $rutaEsperada = route('admin.maestros.asignaciones.cambiar', [
            'maestro_id' => $data['maestro']->id,
            'detalle_id' => $data['detalle']->id,
        ]);

        $rutaEscapada = str_replace('/', '\/', $rutaEsperada);

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->put(route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]), [
                'nuevo_maestro_id' => $data['maestroAlternativo']->id,
                'motivo_cambio' => 'cambio',
                'detalle_id_contexto' => $data['detalle']->id,
            ]);

        $response->assertSee('data-open="1"', false);
        $response->assertSee('data-action="'.$rutaEsperada.'"', false);
        $response->assertSee($rutaEscapada, false);
        $response->assertSee('value="'.$data['maestroAlternativo']->id.'" selected', false);
        $response->assertSee('cambio', false);
    }

    public function test_history_shows_maestro_nombre_completo(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['maestro']->nombre_completo, false);
        $response->assertSee($data['exMaestro']->nombre_completo, false);
    }

    public function test_history_shows_estado_labels(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Activa', false);
        $response->assertSee('Finalizada', false);
    }

    public function test_history_shows_fecha_inicio_formatted(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('15/03/2025', false);
        $response->assertSee('14/06/2025', false);
    }

    public function test_history_shows_no_registrada_for_null_fecha_inicio(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('No registrada', false);
    }

    public function test_history_shows_actual_for_active_sin_fecha_fin(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Actual', false);
    }

    public function test_history_shows_dash_for_null_motivo_cambio(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Traslado de área', false);
        $response->assertSee('—', false);
    }

    public function test_history_shows_migration_for_null_asignado_por(): void
    {
        $data = $this->crearEscenarioBasico();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-05-31',
            'estado' => 'finalizada',
            'motivo_cambio' => 'Reorganización',
            'asignado_por' => null,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Migración inicial', false);
        $response->assertSee('Reorganización', false);
    }

    public function test_history_ordered_most_recent_first(): void
    {
        $data = $this->crearEscenarioBasico();

        $maestroReciente = Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Reciente',
            'apellido' => 'Vista',
            'codigo' => 'MST00010',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $maestroReciente->id,
            'fecha_inicio' => '2025-06-20',
            'fecha_fin' => '2025-06-30',
            'estado' => 'finalizada',
            'asignado_por' => $data['admin']->id,
        ]);

        $maestroAntiguo = Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Antiguo',
            'apellido' => 'Vista',
            'codigo' => 'MST00011',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $maestroAntiguo->id,
            'fecha_inicio' => '2025-01-15',
            'fecha_fin' => '2025-06-19',
            'estado' => 'finalizada',
            'asignado_por' => $data['admin']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $posReciente = strpos($html, 'Reciente Vista');
        $posAntiguo = strpos($html, 'Antiguo Vista');

        $this->assertNotFalse($posReciente, 'Reciente Vista debe aparecer en el HTML.');
        $this->assertNotFalse($posAntiguo, 'Antiguo Vista debe aparecer en el HTML.');
        $this->assertLessThan($posAntiguo, $posReciente, 'Reciente Vista debe aparecer antes que Antiguo Vista.');
    }

    public function test_asignaciones_docentes_eager_loaded(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $maestro = $response->viewData('maestro');

        $this->assertNotNull($maestro);
        $this->assertTrue($maestro->relationLoaded('detalleCursos'));

        $detalle = $maestro->detalleCursos->first();
        $this->assertNotNull($detalle);
        $this->assertTrue($detalle->relationLoaded('asignacionesDocentes'));
    }

    public function test_maestro_and_asignado_por_relations_eager_loaded(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $maestro = $response->viewData('maestro');
        $detalle = $maestro->detalleCursos->first();
        $asignacion = $detalle->asignacionesDocentes->first();

        $this->assertNotNull($asignacion);
        $this->assertTrue($asignacion->relationLoaded('maestro'));
        $this->assertTrue($asignacion->relationLoaded('asignadoPor'));
    }

    public function test_notas_count_available_on_detalle(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $maestro = $response->viewData('maestro');
        $detalle = $maestro->detalleCursos->first();

        $this->assertNotNull($detalle);
        $this->assertArrayHasKey('notas_count', $detalle->getAttributes());
        $this->assertSame(0, $detalle->notas_count);
    }

    public function test_desasignar_form_shown_when_eligible(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Desasignar', false);
        $response->assertSee(route('admin.maestros.desasignar', [$data['maestro']->id, $data['detalle']->id]), false);
    }

    public function test_desasignar_not_shown_when_notes_exist(): void
    {
        $data = $this->crearEscenarioBasico();

        $alumno = Alumno::create([
            'nombre' => 'Test',
            'apellido' => 'Nota',
            'codigo' => 'ALMNOTA01',
        ]);

        Nota::create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $data['detalle']->id,
            'registrado_por' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('No eliminable', false);
        $response->assertDontSee('Desasignar', false);
    }

    public function test_desasignar_not_shown_when_finalized_history(): void
    {
        $data = $this->crearEscenarioConHistorial();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('No eliminable', false);
        $response->assertDontSee('Desasignar', false);
    }

    public function test_no_invalid_or_nested_delete_forms_in_modal(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertSame(1, substr_count($content, '<form'), 'El modal debe tener exactamente un formulario.');
        $this->assertStringContainsString("@method('PUT')", $content);
        $this->assertStringNotContainsString("@method('DELETE')", $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        $modalForm = $xpath->query('//div[@id="modalCambiarMaestro"]//form')->item(0);
        $this->assertNotNull($modalForm, 'El formulario del modal existe en el DOM.');

        $putInputs = $xpath->query('//input[@value="PUT"]');
        $this->assertGreaterThanOrEqual(1, $putInputs->length, 'El formulario del modal tiene spoofing PUT.');
    }

    public function test_dynamic_data_is_escaped_in_blade(): void
    {
        $content = file_get_contents(resource_path('views/admin/partials/modal-cambiar-maestro.blade.php'));

        $this->assertStringNotContainsString('{!!', $content);
        $this->assertStringContainsString('{{ old(\'nuevo_maestro_id\'', $content);
        $this->assertStringContainsString('{{ old(\'motivo_cambio\'', $content);

        $data = $this->crearEscenarioBasico();
        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $this->assertStringNotContainsString('onmouseover="alert', $html);
    }

    public function test_js_does_not_concatenate_routes(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('function abrirModalCambiarMaestro(ruta, detalleId, cursoNombre, programaNombre)', $content);
        $this->assertStringContainsString('form.action = ruta;', $content);

        $blade = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertStringContainsString('@js(route(', $blade);
    }

    public function test_js_uses_textcontent_not_innerhtml(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('textContent', $content);
        $this->assertStringNotContainsString('innerHTML', $content);
    }

    public function test_js_listeners_are_null_safe(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('const modalCambiar = document.getElementById', $content);
        $this->assertStringContainsString('if (modalCambiar)', $content);
        $this->assertStringContainsString('if (modalCambiarAbierto && modalCambiarAbierto', $content);
        $this->assertStringContainsString('if (!modal || !form)', $content);
    }

    public function test_escape_key_closes_both_modals(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('modalAsignarAbierto', $content);
        $this->assertStringContainsString('modalCambiarAbierto', $content);
        $this->assertStringContainsString('cerrarModalAsignarCurso()', $content);
        $this->assertStringContainsString('cerrarModalCambiarMaestro()', $content);
    }

    public function test_window_functions_exposed_for_cambiar(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('window.abrirModalCambiarMaestro = abrirModalCambiarMaestro', $content);
        $this->assertStringContainsString('window.cerrarModalCambiarMaestro = cerrarModalCambiarMaestro', $content);
    }

    public function test_get_request_does_not_modify_assignments(): void
    {
        $data = $this->crearEscenarioBasico();

        $antes = AsignacionDocente::count();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertOk();

        $despues = AsignacionDocente::count();
        $this->assertSame($antes, $despues);
    }

    public function test_guest_redirected_to_login_for_show(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertRedirect(route('login'));
    }

    public function test_docente_and_alumno_get_403(): void
    {
        $data = $this->crearEscenarioBasico();

        $response = $this->actingAs($data['maestroUser'])
            ->get(route('admin.maestros.show', $data['maestro']->id));
        $response->assertStatus(403);

        $alumno = User::factory()->create(['role' => 'alumno']);
        $response = $this->actingAs($alumno)
            ->get(route('admin.maestros.show', $data['maestro']->id));
        $response->assertStatus(403);
    }
}
