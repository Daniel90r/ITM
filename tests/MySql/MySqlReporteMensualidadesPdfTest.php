<?php

namespace Tests\MySql;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Mensualidad;
use App\Models\Pago;
use App\Services\ReportesAdministrativos;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ReporteMensualidadesPdfTest;

class MySqlReporteMensualidadesPdfTest extends ReporteMensualidadesPdfTest
{
    protected function beforeRefreshingDatabase(): void
    {
        if (! getenv('ITM_MYSQL_MANIFEST') || config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires owned MySQL environment');
        }
        $m = json_decode(file_get_contents(getenv('ITM_MYSQL_MANIFEST')), true, flags: JSON_THROW_ON_ERROR);
        $a = DB::selectOne('SELECT DATABASE() db, @@server_uuid uuid, CURRENT_USER() identity');
        $this->assertContains($a->db, $m['owned']);
        $this->assertNotContains($a->db, $m['preexisting']);
        $this->assertSame($m['server']['uuid'], $a->uuid);
        $this->assertSame($m['username'].'@localhost', $a->identity);
    }

    public function test_totales_alumnos_unicos_y_centavos_sin_duplicar_pagos(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $pagada = Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'Pagado', 'monto' => '20.10']);
        Pago::create(['mensualidad_id' => $pagada->id, 'fecha_pago' => now(), 'monto_pagado' => $pagada->monto]);
        Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'Pendiente', 'monto' => '10.20']);
        Mensualidad::factory()->create(['estado' => 'Pendiente', 'monto' => '0.10']);
        $r = app(ReportesAdministrativos::class)->mensualidadesPdf([]);
        $this->assertCount(3, $r['filas']);
        $this->assertSame(2, $r['totalAlumnos']);
        $this->assertSame(1, $r['pagosRegistrados']);
        $this->assertSame(2, $r['pendientes']);
        $this->assertSame(1030, $r['pendienteCentavos']);
        $this->assertSame(2010, $r['pagadoCentavos']);
        $this->assertSame(0, $r['incidencias']);
        try {
            Pago::create(['mensualidad_id' => $pagada->id, 'fecha_pago' => now(), 'monto_pagado' => $pagada->monto]);
            $this->fail('El esquema debe impedir pagos duplicados por mensualidad.');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
        $this->assertSame(1, app(ReportesAdministrativos::class)->mensualidadesPdf([])['pagosRegistrados']);
    }
}
