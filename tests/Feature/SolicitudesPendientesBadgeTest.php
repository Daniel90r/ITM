<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SolicitudesPendientesBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->curso = Curso::factory()->create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'activo' => true,
        ]);
    }

    private function crearSolicitudes(int $cantidad): array
    {
        $ids = [];

        foreach (range(1, $cantidad) as $i) {
            // El contador necesita solicitudes aprobables: una fecha aleatoria
            // puede crear un menor sin encargado y rechazar correctamente la aprobación.
            $alumno = Alumno::factory()->create([
                'fecha_nacimiento' => now()->subYears(20)->toDateString(),
            ]);

            $ids[] = Inscripcion::create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => false,
                'estado' => 'pendiente',
            ])->id;
        }

        return $ids;
    }

    private function badgeHtml(string $html): string
    {
        preg_match('/<span\s+data-solicitudes-badge.*?<\/span>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    private function assertBadgeOculto(string $html): void
    {
        $badge = $this->badgeHtml($html);

        $this->assertNotSame('', $badge, 'el badge debe existir en el sidebar');
        $this->assertStringContainsString('display:none', $badge);
        $this->assertStringContainsString('data-count="0"', $badge);
    }

    private function assertBadgeVisible(string $html, int $count): void
    {
        $badge = $this->badgeHtml($html);

        $this->assertNotSame('', $badge, 'el badge debe existir en el sidebar');
        $this->assertStringContainsString('display:inline-flex', $badge);
        $this->assertStringContainsString('data-count="' . $count . '"', $badge);
        $this->assertStringContainsString('>' . $count . '</span>', $badge);
    }

    // ── Endpoint ──────────────────────────────────────────────────────────────

    #[Test]
    public function test_endpoint_devuelve_cero_cuando_no_hay_pendientes(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'));

        $response->assertOk();
        $response->assertExactJson(['count' => 0]);
    }

    #[Test]
    public function test_endpoint_devuelve_uno_con_una_pendiente(): void
    {
        $this->crearSolicitudes(1);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'));

        $response->assertOk();
        $response->assertExactJson(['count' => 1]);
    }

    #[Test]
    public function test_endpoint_devuelve_cinco_con_cinco_pendientes(): void
    {
        $this->crearSolicitudes(5);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'));

        $response->assertOk();
        $response->assertExactJson(['count' => 5]);
    }

    #[Test]
    public function test_endpoint_solo_devuelve_el_contador(): void
    {
        $this->crearSolicitudes(3);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'));

        $response->assertOk();
        $response->assertJsonCount(1);
        $this->assertSame(['count'], array_keys($response->json()));
    }

    #[Test]
    public function test_endpoint_ignora_aprobadas_y_rechazadas(): void
    {
        $ids = $this->crearSolicitudes(4);

        Inscripcion::whereIn('id', [$ids[0], $ids[1]])->update(['estado' => 'aprobada']);
        Inscripcion::whereIn('id', [$ids[2], $ids[3]])->update(['estado' => 'rechazada']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'));

        $response->assertOk();
        $response->assertExactJson(['count' => 0]);
    }

    // ── Badge en el sidebar ───────────────────────────────────────────────────

    #[Test]
    public function test_badge_oculto_en_cursos_sin_pendientes(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cursos'));

        $response->assertOk();
        $this->assertBadgeOculto($response->getContent());
    }

    #[Test]
    public function test_badge_muestra_1_pendiente(): void
    {
        $this->crearSolicitudes(1);

        $response = $this->actingAs($this->admin)->get(route('admin.cursos'));

        $response->assertOk();
        $this->assertBadgeVisible($response->getContent(), 1);
    }

    #[Test]
    public function test_badge_muestra_5_pendientes(): void
    {
        $this->crearSolicitudes(5);

        $response = $this->actingAs($this->admin)->get(route('admin.cursos'));

        $response->assertOk();
        $this->assertBadgeVisible($response->getContent(), 5);
    }

    #[Test]
    public function test_badge_usa_la_misma_fuente_que_la_pagina_de_solicitudes(): void
    {
        $this->crearSolicitudes(2);

        $pagina = $this->actingAs($this->admin)->get(route('admin.solicitudes'));

        $pagina->assertOk();
        $pagina->assertSee('Pendientes', false);

        $html = $pagina->getContent();
        $this->assertBadgeVisible($html, 2);

        preg_match('/data-count="(\d+)"/', $this->badgeHtml($html), $m);
        $contadorDelBadge = (int) $m[1];

        $contadorDelEndpoint = $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->json('count');

        $contadorDeLaTarjeta = Inscripcion::pendiente()->count();

        $this->assertSame($contadorDeLaTarjeta, $contadorDelBadge);
        $this->assertSame($contadorDeLaTarjeta, $contadorDelEndpoint);
    }

    // ── Polling: baja el contador al aprobar / rechazar ───────────────────────

    #[Test]
    public function test_nueva_solicitud_aumenta_el_contador_del_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertExactJson(['count' => 0]);

        $this->crearSolicitudes(1);

        $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertExactJson(['count' => 1]);
    }

    #[Test]
    public function test_aprobar_solicitud_disminuye_el_contador(): void
    {
        $ids = $this->crearSolicitudes(2);

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $ids[0]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertExactJson(['count' => 1]);
    }

    #[Test]
    public function test_rechazar_solicitud_disminuye_el_contador(): void
    {
        $ids = $this->crearSolicitudes(2);

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.rechazar', $ids[0]), [
                'motivo_rechazo' => 'Sin documentos',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertExactJson(['count' => 1]);
    }

    #[Test]
    public function test_ultima_pendiente_resuelta_deja_el_badge_oculto(): void
    {
        $ids = $this->crearSolicitudes(1);

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $ids[0]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertExactJson(['count' => 0]);

        $response = $this->actingAs($this->admin)->get(route('admin.cursos'));

        $response->assertOk();
        $this->assertBadgeOculto($response->getContent());
    }

    // ── Seguridad ────────────────────────────────────────────────────────────

    #[Test]
    public function test_usuario_no_admin_no_puede_consultar_el_endpoint(): void
    {
        $alumno = User::factory()->create([
            'email' => 'alumno@test.com',
            'role' => 'alumno',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($alumno)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertForbidden();
    }

    #[Test]
    public function test_docente_no_puede_consultar_el_endpoint(): void
    {
        $docente = User::factory()->create([
            'email' => 'docente@test.com',
            'role' => 'docente',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($docente)
            ->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertForbidden();
    }

    #[Test]
    public function test_usuario_no_autenticado_no_puede_consultar_el_endpoint(): void
    {
        $this->getJson(route('admin.solicitudes.pendientesCount'))
            ->assertUnauthorized();
    }

    // ── Integración del JavaScript de polling ────────────────────────────────

    #[Test]
    public function test_el_js_de_polling_esta_registrado_en_el_entrypoint_global(): void
    {
        $appJs = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("import './admin/solicitudesBadge';", $appJs);

        $badgeJs = base_path('resources/js/admin/solicitudesBadge.js');
        $this->assertFileExists($badgeJs);

        $contenido = file_get_contents($badgeJs);
        $this->assertStringContainsString('30000', $contenido);
        $this->assertStringContainsString('data-solicitudes-badge', $contenido);
        $this->assertStringContainsString('setInterval', $contenido);
    }

    #[Test]
    public function test_el_badge_expone_la_url_del_endpoint_para_el_polling(): void
    {
        $this->crearSolicitudes(1);

        $response = $this->actingAs($this->admin)->get(route('admin.cursos'));
        $badge = $this->badgeHtml($response->getContent());

        $this->assertStringContainsString(
            'data-url="' . route('admin.solicitudes.pendientesCount') . '"',
            $badge
        );
    }
}
