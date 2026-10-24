<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class MySqlEstadoPersonaTest extends EstadoPersonaTest
{
    protected function beforeRefreshingDatabase(): void
    {
        if (! getenv('ITM_MYSQL_MANIFEST') || config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the owned MySQL bootstrap');
        }
        $manifest = json_decode(file_get_contents(getenv('ITM_MYSQL_MANIFEST')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('mysql', config('database.default'));
        $actual = DB::selectOne('SELECT DATABASE() db, @@server_uuid uuid, CURRENT_USER() identity');
        $this->assertContains($actual->db, $manifest['owned']);
        $this->assertNotContains($actual->db, $manifest['preexisting']);
        $this->assertNotSame('itm', $actual->db);
        $this->assertSame($manifest['server']['uuid'], $actual->uuid);
        $this->assertSame($manifest['username'].'@localhost', $actual->identity);
        $this->assertNotSame(base_path('storage'), storage_path());
    }
}
