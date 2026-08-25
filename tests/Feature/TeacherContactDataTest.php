<?php

namespace Tests\Feature;

use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherContactDataTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Maestro::preventLazyLoading(false);
        parent::tearDown();
    }

    private function crearMaestroConUsuario(string $dui = '01234567-8', string $telefono = '7123-4567'): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => $telefono,
            'dui' => $dui,
            'codigo' => 'MST'.uniqid(),
            'activo' => true,
        ]);
    }

    public function test_admin_can_create_maestro_with_valid_contact_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'email' => 'carlos.mendoza@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro registrado correctamente.');

        $this->assertDatabaseHas('maestros', [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'carlos.mendoza@test.com',
            'role' => 'docente',
        ]);
    }

    public function test_store_rejects_missing_telefono_and_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'email' => 'carlos.mendoza@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrorsIn('storeMaestro', ['telefono', 'dui']);

        $this->assertDatabaseMissing('users', ['email' => 'carlos.mendoza@test.com']);
        $this->assertDatabaseMissing('maestros', ['nombre' => 'Carlos']);
    }

    public function test_store_rejects_invalid_telefono(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidTelefonos = ['1123-4567', '3123-4567', '5123-4567', '8123-4567', '9123-4567', '71234567', '7123-456', '7123-45678'];

        foreach ($invalidTelefonos as $i => $telefono) {
            $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
                'nombre' => 'Carlos',
                'apellido' => 'Mendoza',
                'telefono' => $telefono,
                'dui' => '01234567-8',
                'email' => "test{$i}@test.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrorsIn('storeMaestro', ['telefono']);
        }

        $this->assertDatabaseMissing('maestros', ['telefono' => $invalidTelefonos[0]]);
    }

    public function test_store_rejects_invalid_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidDuis = ['012345678', '01234567', '01234567-89', '0123-4567-8'];

        foreach ($invalidDuis as $i => $dui) {
            $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
                'nombre' => 'Carlos',
                'apellido' => 'Mendoza',
                'telefono' => '7123-4567',
                'dui' => $dui,
                'email' => "test{$i}@test.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrorsIn('storeMaestro', ['dui']);
        }

        foreach ($invalidDuis as $dui) {
            $this->assertDatabaseMissing('maestros', ['dui' => $dui]);
        }
    }

    public function test_store_rejects_duplicate_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->crearMaestroConUsuario('01234567-8');

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'New',
            'apellido' => 'Teacher',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrorsIn('storeMaestro', ['dui']);
        $this->assertDatabaseMissing('maestros', ['nombre' => 'New', 'dui' => '01234567-8']);
    }

    public function test_validation_failure_does_not_create_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $usersBefore = User::count();
        $maestrosBefore = Maestro::count();

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '1234-5678',
            'dui' => 'invalid',
            'email' => 'invalid@test.com',
            'password' => 'short',
            'password_confirmation' => 'nomatch',
        ]);

        $response->assertSessionHasErrorsIn('storeMaestro');
        $this->assertSame($usersBefore, User::count());
        $this->assertSame($maestrosBefore, Maestro::count());
    }

    public function test_admin_can_update_telefono_and_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');
        $originalPassword = $maestro->user->password;

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7234-5678',
            'dui' => '01234568-9',
            'codigo' => 'MST00001',
            'email' => $maestro->user->email,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro actualizado correctamente.');

        $this->assertDatabaseHas('maestros', [
            'id' => $maestro->id,
            'telefono' => '7234-5678',
            'dui' => '01234568-9',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $maestro->user_id,
            'password' => $originalPassword,
        ]);
    }

    public function test_update_allows_same_dui_for_maestro(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'MST00001',
            'email' => $maestro->user->email,
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro actualizado correctamente.');

        $this->assertDatabaseHas('maestros', [
            'id' => $maestro->id,
            'dui' => '01234567-8',
        ]);
    }

    public function test_update_rejects_dui_of_another_maestro(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro1 = $this->crearMaestroConUsuario('01234567-8', '7123-4567');
        $maestro2 = $this->crearMaestroConUsuario('01234568-9', '7234-5678');
        $maestro2->update(['nombre' => 'Second']);

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro1->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234568-9',
            'codigo' => 'MST00001',
            'email' => $maestro1->user->email,
        ]);

        $response->assertSessionHasErrorsIn('updateMaestro', ['dui']);
        $this->assertDatabaseHas('maestros', [
            'id' => $maestro1->id,
            'dui' => '01234567-8',
        ]);
    }

    public function test_update_rejects_missing_telefono_and_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'codigo' => 'MST00001',
            'email' => $maestro->user->email,
        ]);

        $response->assertSessionHasErrorsIn('updateMaestro', ['telefono', 'dui']);
    }

    public function test_password_not_changed_when_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();
        $originalPassword = $maestro->user->password;

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'MST00001',
            'email' => $maestro->user->email,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro actualizado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $maestro->user_id,
            'password' => $originalPassword,
        ]);
    }

    public function test_get_admin_maestros_eager_loads_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->crearMaestroConUsuario();

        Maestro::preventLazyLoading();

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));
        $response->assertStatus(200);
    }

    public function test_get_admin_maestros_show_eager_loads_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        Maestro::preventLazyLoading();

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros.show', $maestro->id));
        $response->assertStatus(200);
    }

    public function test_creation_form_has_telefono_and_dui_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertSee('name="telefono"', false);
        $response->assertSee('name="dui"', false);
        $response->assertSee('id="telefonoNuevoMaestro"', false);
        $response->assertSee('id="duiNuevoMaestro"', false);
    }

    public function test_edit_form_has_edit_telefono_and_dui_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertSee('id="editTelefono"', false);
        $response->assertSee('id="editDui"', false);
        $response->assertSee('name="telefono"', false);
        $response->assertSee('name="dui"', false);
    }

    public function test_store_accepts_valid_telefonos_with_all_prefixes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $cases = [
            ['2123-4567', '01234567-8', 'valid2@test.com'],
            ['6123-4567', '01234568-9', 'valid6@test.com'],
            ['7123-4567', '01234569-0', 'valid7@test.com'],
        ];

        foreach ($cases as [$telefono, $dui, $email]) {
            $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
                'nombre' => 'Carlos',
                'apellido' => 'Mendoza',
                'telefono' => $telefono,
                'dui' => $dui,
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertRedirect(route('admin.maestros'));
        }

        $this->assertDatabaseHas('maestros', ['telefono' => '2123-4567']);
        $this->assertDatabaseHas('maestros', ['telefono' => '6123-4567']);
        $this->assertDatabaseHas('maestros', ['telefono' => '7123-4567']);
    }

    public function test_store_rejects_telefono_starting_with_8(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '8123-4567',
            'dui' => '01234567-8',
            'email' => 'rechazo8@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrorsIn('storeMaestro', ['telefono']);
        $this->assertDatabaseMissing('maestros', ['telefono' => '8123-4567']);
    }

    public function test_store_rejects_telefono_starting_with_9(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '9123-4567',
            'dui' => '01234567-8',
            'email' => 'rechazo9@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrorsIn('storeMaestro', ['telefono']);
        $this->assertDatabaseMissing('maestros', ['telefono' => '9123-4567']);
    }

    public function test_store_succeeds_without_sending_codigo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '7123-4567',
            'dui' => '09876543-4',
            'email' => 'nocodigo@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro registrado correctamente.');

        $this->assertDatabaseHas('maestros', [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '7123-4567',
            'dui' => '09876543-4',
        ]);
    }

    public function test_controller_generates_and_saves_codigo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.maestros.store'), [
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'telefono' => '7123-4567',
            'dui' => '09876544-5',
            'email' => 'genera@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $maestro = Maestro::where('dui', '09876544-5')->first();
        $this->assertNotNull($maestro);
        $this->assertNotEmpty($maestro->codigo);
    }

    public function test_edit_modal_button_passes_seven_arguments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->crearMaestroConUsuario('01234567-8', '7123-4567');

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertSee('abrirModalEditarMaestro(', false);
        $response->assertSee('7123-4567', false);
        $response->assertSee('01234567-8', false);
    }

    public function test_modals_js_exists_with_seven_params(): void
    {
        $path = resource_path('js/admin/modals.js');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString(
            'function abrirModalEditarMaestro(id, nombre, apellido, telefono, dui, codigo, email)',
            $content
        );
        $this->assertStringContainsString("getElementById('editTelefono')", $content);
        $this->assertStringContainsString("getElementById('editDui')", $content);
        $this->assertStringContainsString("getElementById('editPassword')", $content);
        $this->assertStringContainsString("getElementById('editPasswordConfirmation')", $content);
    }

    public function test_update_works_without_sending_codigo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'UpdatedName',
            'apellido' => 'UpdatedLastName',
            'telefono' => '6123-4567',
            'dui' => '98765432-1',
            'email' => $maestro->user->email,
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro actualizado correctamente.');

        $this->assertDatabaseHas('maestros', [
            'id' => $maestro->id,
            'nombre' => 'UpdatedName',
        ]);
    }

    public function test_update_ignores_manipulated_codigo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');
        $originalCodigo = $maestro->codigo;

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'HACKED123',
            'email' => $maestro->user->email,
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $maestro->refresh();
        $this->assertSame($originalCodigo, $maestro->codigo);
        $this->assertNotSame('HACKED123', $maestro->codigo);
    }

    public function test_update_modifies_telefono_dui_nombre_apellido(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');
        $originalCodigo = $maestro->codigo;

        $response = $this->actingAs($admin)->put(route('admin.maestros.update', $maestro->id), [
            'nombre' => 'NewName',
            'apellido' => 'NewApellido',
            'telefono' => '6234-5678',
            'dui' => '12345678-0',
            'email' => $maestro->user->email,
        ]);

        $response->assertRedirect(route('admin.maestros'));
        $response->assertSessionHas('success', 'Maestro actualizado correctamente.');

        $maestro->refresh();
        $this->assertSame('NewName', $maestro->nombre);
        $this->assertSame('NewApellido', $maestro->apellido);
        $this->assertSame('6234-5678', $maestro->telefono);
        $this->assertSame('12345678-0', $maestro->dui);
        $this->assertSame($originalCodigo, $maestro->codigo);
    }

    public function test_admin_maestros_page_does_not_render_asignar_curso_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertDontSee('id="modalAsignarCurso"', false);
    }

    public function test_asignar_cursos_link_points_to_show_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertSee(route('admin.maestros.show', $maestro->id), false);
    }

    public function test_creation_form_renders_password_as_type_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $html = $response->getContent();
        $this->assertStringContainsString('type="password" class="campo-secreto" name="password"', $html);
    }

    public function test_creation_form_renders_password_confirmation_as_type_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $html = $response->getContent();
        $this->assertStringContainsString('type="password" class="campo-secreto" name="password_confirmation"', $html);
    }

    public function test_edit_form_renders_password_fields_as_type_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $html = $response->getContent();
        $this->assertStringContainsString('type="password" class="campo-secreto" id="editPassword"', $html);
        $this->assertStringContainsString('type="password" class="campo-secreto" id="editPasswordConfirmation"', $html);
    }

    public function test_no_password_input_uses_type_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $html = $response->getContent();
        $this->assertStringNotContainsString('type="text" class="campo-secreto" name="password"', $html);
        $this->assertStringNotContainsString('type="text" class="campo-secreto" name="password_confirmation"', $html);
        $this->assertStringNotContainsString('type="text" class="campo-secreto" id="editPassword"', $html);
        $this->assertStringNotContainsString('type="text" class="campo-secreto" id="editPasswordConfirmation"', $html);
    }
}
