<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Tests\Feature\ReactivarInscripcionTest;

class MySqlReactivarInscripcionTest extends ReactivarInscripcionTest
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

    public function test_migracion_pendiente_bloquea_operacion_sin_romper_listado(): void
    {
        $this->markTestSkipped('This legacy case drops a table; covered separately in the owned DDL database.');
    }
}
