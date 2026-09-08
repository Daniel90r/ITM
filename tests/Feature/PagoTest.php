<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Mensualidad;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PagoTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_no_se_pueden_registrar_dos_pagos_para_la_misma_mensualidad(): void
    {
        $mensualidad = $this->crearMensualidad();

        Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => 100.00,
        ]);

        $this->expectException(QueryException::class);

        Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => 200.00,
        ]);
    }

    public function test_no_se_puede_eliminar_una_mensualidad_que_tiene_pago(): void
    {
        $mensualidad = $this->crearMensualidad();

        Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => 100.00,
        ]);

        $this->expectException(QueryException::class);

        $mensualidad->delete();
    }

    public function test_pago_guarda_fecha_pago_y_monto(): void
    {
        $mensualidad = $this->crearMensualidad();

        $pago = Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => 250.00,
            'observacion' => 'Pago de prueba',
        ]);

        $this->assertDatabaseHas('pagos', [
            'id' => $pago->id,
            'mensualidad_id' => $mensualidad->id,
            'monto_pagado' => 250.00,
        ]);

        $this->assertEquals(now()->toDateString(), $pago->fecha_pago->toDateString());
        $this->assertEquals(250.00, $pago->monto_pagado);
    }

    public function test_pago_no_crea_boletas_automaticamente(): void
    {
        $mensualidad = $this->crearMensualidad();

        Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => 100.00,
        ]);

        $this->assertDatabaseMissing('boletas', [
            'inscripcion_id' => DB::table('inscripciones')->where('alumno_id', $mensualidad->alumno_id)->value('id'),
        ]);
    }

    private function crearMensualidad(): Mensualidad
    {
        $alumno = Alumno::create([
            'nombre' => 'Test',
            'apellido' => 'Pago',
            'codigo' => 'ALM00001',
            'fecha_nacimiento' => '2005-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);

        $curso = Curso::create([
            'nombre' => 'Test Curso',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        return Mensualidad::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'mes' => '09',
            'anio' => 2025,
            'monto' => 200.00,
            'estado' => 'Pendiente',
            'fecha_vencimiento' => '2025-09-30',
        ]);
    }
}
