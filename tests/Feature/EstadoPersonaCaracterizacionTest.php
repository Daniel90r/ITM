<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoPersonaCaracterizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_estado_del_expediente_no_modifica_la_cuenta_ni_el_otro_expediente(): void
    {
        $user = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create(['user_id' => $user->id]);
        $original = $user->fresh()->getAttributes();
        $alumno->update(['activo' => false]);
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseHas('alumnos', ['id' => $alumno->id, 'activo' => 0]);
        $alumno->update(['activo' => true]);
        $this->assertSame($original, $user->fresh()->getAttributes());
    }

    public function test_roles_no_conceden_acceso_administrativo(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'docente']);
        Maestro::factory()->create(['user_id' => $user->id]);
        $this->withSession(['auth_version' => 1])->actingAs($user)
            ->get(route('admin.alumnos'))->assertForbidden();
    }
}
