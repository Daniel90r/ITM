<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\DocumentosInscripcionTest;
use Tests\TestCase;

class MySqlDocumentosInscripcionTest extends DocumentosInscripcionTest
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

    protected function setUp(): void
    {
        TestCase::setUp();
        Storage::fake('inscripciones');
        Storage::fake('public');
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.legacy_read_enabled' => false]);
    }
}
