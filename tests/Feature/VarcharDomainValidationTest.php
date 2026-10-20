<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VarcharDomainValidationTest extends TestCase
{
    use RefreshDatabase;

    // ════════════════════════════════════════════════════════════════════════════════
    // TESTS FUNCIONALES DE VALIDACIÓN
    // ════════════════════════════════════════════════════════════════════════════════

    public function test_nombre_max_100_accepted_101_rejected()
    {
        // Test using profile.update route which validates users.name with max:100
        $user = \App\Models\User::factory()->create([
            'role' => 'admin',
            'name' => 'Original Name',
        ]);

        $validName = str_repeat('a', 100);
        $invalidName = str_repeat('a', 101);

        // Caso A: 100 caracteres - debe pasar
        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $validName,
                'email' => $user->email,
            ]);

        $response->assertRedirect(); // 302 redirect on success
        $response->assertSessionHasNoErrors('name');

        $user->refresh();
        $this->assertEquals($validName, $user->name);

        // Caso B: 101 caracteres - debe fallar
        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $invalidName,
                'email' => $user->email,
            ]);

        $response->assertRedirect(); // 302 redirect on validation failure
        $response->assertSessionHasErrors('name');

        $user->refresh();
        $this->assertNotEquals($invalidName, $user->name);
    }

    public function test_director_dui_validation()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        // Caso válido: DUI 00000000-0
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test',
                'dui' => '00000000-0',
                'telefono' => '2000-0000',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors('dui');
        $this->assertDatabaseHas('directores', ['dui' => '00000000-0']);

        // Caso inválido: solo números (formato incorrecto)
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test 2',
                'dui' => '123',
                'telefono' => '2000-0000',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('dui');

        // Caso inválido: muy largo (11 caracteres)
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test 3',
                'dui' => '12345678901',
                'telefono' => '2000-0000',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('dui');
    }

    public function test_director_telefono_validation()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        // Caso válido: 0000-0000
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test Tel',
                'dui' => '00000000-1',
                'telefono' => '2000-0000',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors('telefono');
        $this->assertDatabaseHas('directores', ['telefono' => '2000-0000']);

        // Caso inválido: muy corto (4 dígitos)
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test Tel 2',
                'dui' => '00000000-2',
                'telefono' => '1234',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('telefono');

        // Caso inválido: 10 dígitos sin guión
        $response = $this->actingAs($admin)
            ->post(route('admin.directores.store'), [
                'nombre' => 'Director Test Tel 3',
                'dui' => '00000000-3',
                'telefono' => '1234567890',
                'email' => 'dir' . time() . '@test.com',
                'fecha_inicio' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('telefono');
    }

    public function test_conducta_accepted_values()
    {
        // Reutilizar setup similar a DocenteNotasValidacionTest
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $docenteUser = \App\Models\User::factory()->create(['role' => 'docente']);
        $maestro = \App\Models\Maestro::create([
            'user_id' => $docenteUser->id,
            'nombre' => 'Maestro',
            'apellido' => 'Test',
            'codigo' => 'TST001',
            'activo' => true,
        ]);

        $curso = \App\Models\Curso::factory()->create(['nivel' => 'Matutino']);
        $materia = \App\Models\Materia::factory()->create();
        $detalle = \App\Models\DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestro->id,
        ]);

        // Asignación docente activa
        \App\Models\AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'estado' => 'activa',
            'asignado_por' => $admin->id,
        ]);

        $alumnoUser = \App\Models\User::factory()->create(['role' => 'alumno']);
        $alumno = \App\Models\Alumno::factory()->create(['user_id' => $alumnoUser->id]);
        \App\Models\Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
            'fecha_inscripcion' => now()->toDateString(),
        ]);

        foreach (['B', 'MB', 'E', null] as $conducta) {
            $response = $this->actingAs($docenteUser)
                ->post(route('docente.notas.guardar', ['detalle' => $detalle->id]), [
                    'notas' => [
                        $alumno->id => [
                            'conducta' => $conducta,
                            'laboratorio' => null,
                            'examen_teorico' => null,
                            'practica' => null,
                        ],
                    ],
                ]);

            $this->assertNotEquals(422, $response->status(), "conducta='$conducta' debe ser aceptada");
        }
    }
}