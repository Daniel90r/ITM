<?php

namespace Tests\Feature;

use App\Models\{Alumno, Maestro, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionalFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_iconos_validos_y_multirresolucion(): void
    {
        foreach ([32, 180, 192] as $size) {
            $image = getimagesize(public_path('favicons/itm-'.$size.'.png'));
            $this->assertSame([$size, $size], [$image[0], $image[1]]);
            $this->assertSame('image/png', $image['mime']);
        }
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], unpack('vreserved/vtype/vcount', substr($ico, 0, 6)));
        foreach ([16, 32, 48] as $i => $size) {
            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vlength/Voffset', substr($ico, 6 + $i * 16, 16));
            $image = getimagesizefromstring(substr($ico, $entry['offset'], $entry['length']));
            $this->assertSame([$size, $size], [$image[0], $image[1]]);
            $this->assertSame($size, $entry['width']);
        }
    }

    public function test_autenticacion_reportes_solicitudes_y_roles_comparten_iconos(): void
    {
        $this->withoutVite();
        $this->checkIcons($this->get(route('login'))->assertOk()->getContent(), 'login');
        $this->checkIcons($this->get(route('password.request'))->assertOk()->getContent(), 'recuperar');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->checkIcons($this->get(route('admin.dashboard'))->assertOk()->getContent(), 'admin');
        foreach (['alumnos', 'cursos', 'asistencias', 'mensualidades', 'maestros'] as $type) {
            $this->checkIcons($this->get(route('admin.reportes.show', $type))->assertOk()->getContent(), $type);
        }
        $this->checkIcons($this->get(route('admin.solicitudes'))->assertOk()->getContent(), 'solicitudes');
        $teacher = User::factory()->create(['role' => 'docente']);
        Maestro::factory()->create(['user_id' => $teacher->id]);
        $this->actingAs($teacher);
        $this->checkIcons($this->get(route('docente.dashboard'))->assertOk()->getContent(), 'docente');
        $student = User::factory()->create(['role' => 'alumno']);
        Alumno::factory()->create(['user_id' => $student->id]);
        $this->actingAs($student);
        $this->checkIcons($this->get(route('alumno.dashboard'))->assertOk()->getContent(), 'alumno');
    }

    private function checkIcons(string $html, string $name): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//head/link[@rel="icon" or @rel="apple-touch-icon"]');
        $this->assertCount(4, $links);
        foreach ($links as $link) {
            $url = $link->getAttribute('href');
            $this->assertStringContainsString('?v='.substr(hash_file('sha256', public_path('favicon.ico')), 0, 12), $url);
            $this->assertFileExists(public_path(ltrim(parse_url($url, PHP_URL_PATH), '/')));
        }
        $this->assertSame(1, $xpath->query('//head/title')->length);
        $this->assertSame('ITM Aguilares', $xpath->query('//head/title')->item(0)->textContent);
        // Solo metadatos de páginas renderizadas con SQLite; sin datos de alumnos.
        if (getenv('ITM_EXPORT_FAVICON_FIXTURES') === '1') {
            $directory = base_path('storage/app/reportes-referencia/favicon');
            if (!is_dir($directory)) mkdir($directory, 0700, true);
            $head = '';
            foreach ($links as $link) $head .= $dom->saveHTML($link);
            file_put_contents($directory.'/'.$name.'.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>ITM - '.$name.'</title>'.$head.'</head><body>Prueba aislada del favicon ITM</body></html>');
            if ($name === 'login') {
                $pdf = app('dompdf.wrapper')->loadHTML('<!doctype html><html><head><title>Prueba PDF ITM</title></head><body>Documento ficticio para comprobar el visor PDF. Sin datos personales.</body></html>');
                file_put_contents($directory.'/visor-prueba.pdf', $pdf->output());
            }
        }
    }
}
