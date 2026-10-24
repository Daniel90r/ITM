<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Tests\Feature\ReporteAlumnosPdfTest;

class MySqlReporteAlumnosPdfTest extends ReporteAlumnosPdfTest
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
}
