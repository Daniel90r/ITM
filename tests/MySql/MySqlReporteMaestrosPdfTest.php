<?php

namespace Tests\MySql;

use App\Models\AsignacionDocente;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ReporteMaestrosPdfTest;

class MySqlReporteMaestrosPdfTest extends ReporteMaestrosPdfTest
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

    public function test_multipagina_textos_largos_escape_y_consultas_solo_lectura(): void
    {
        $m = Maestro::factory()->create(['nombre' => '<script>alert(1)</script>']);
        $d = DetalleCurso::factory()->create(['maestro_id' => $m->id]);
        AsignacionDocente::create(['maestro_id' => $m->id, 'detalle_curso_id' => $d->id, 'estado' => 'activa', 'motivo_cambio' => str_repeat('Texto largo. ', 18)]);
        Maestro::factory()->count(40)->create();
        $r = app(ReportesAdministrativos::class)->maestrosPdf([]);
        $this->assertSame(41, $r['totalDocentes']);
        $html = view('admin.reportes.pdf.maestros', $r + ['emitido' => now(), 'filtros' => []])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $pdf = Pdf::loadHTML($html)->setPaper('letter');
        $pdf->render();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }
}
