<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, CursoHorario, Inscripcion, User};
use App\Services\HorariosCurso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CursoHorariosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-18 10:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Curso Horario Ficticio', 'seccion' => 'A', 'nivel' => 'Matutino',
            'anio_lectivo' => 2026, 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-01-31'], $extra);
    }

    private static function bloque(int $dia = 1, string $inicio = '07:30', string $fin = '09:30'): array
    {
        return ['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin];
    }

    public function test_crear_varios_bloques_consecutivos_y_ordenarlos(): void
    {
        $this->post(route('admin.cursos.store'), $this->datos(['horarios' => [
            self::bloque(3), self::bloque(1, '10:00', '12:00'), self::bloque(1, '08:00', '10:00'),
        ]]))->assertSessionHasNoErrors()->assertRedirect(route('admin.cursos'));
        $curso = Curso::firstOrFail();
        $this->assertCount(3, $curso->horarios);
        $this->assertSame(['Lunes: 08:00–10:00', 'Lunes: 10:00–12:00', 'Miércoles: 07:30–09:30'], app(HorariosCurso::class)->lineas($curso));
        $this->get(route('admin.cursos.show', $curso))->assertOk()->assertSee('Lunes: 08:00–10:00');
        $this->get(route('admin.cursos.edit', $curso))->assertOk()->assertSee('value="08:00"', false);
        $this->exportar('nuevo', $this->get(route('admin.cursos'))->assertOk()->getContent());
        $this->exportar('editar', $this->get(route('admin.cursos.edit', $curso))->assertOk()->getContent());
    }

    public function test_editar_y_vaciar_sin_alterar_otras_tablas_ni_documentos(): void
    {
        Storage::fake('public');
        $curso = Curso::factory()->create($this->datos());
        $curso->horarios()->create(self::bloque());
        $alumno = Alumno::factory()->create();
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'documento_path' => 'solicitudes/historico.pdf']);
        Storage::disk('public')->put('solicitudes/historico.pdf', 'Documento histórico ficticio inmutable');
        $archivoAntes = Storage::disk('public')->get('solicitudes/historico.pdf');
        $tablas = ['alumnos', 'maestros', 'notas', 'asistencias', 'inscripciones', 'mensualidades', 'detalle_cursos'];
        $antes = []; foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        $camposAntes = array_intersect_key($curso->fresh()->getRawOriginal(), $this->datos());
        $this->put(route('admin.cursos.update', $curso), $this->datos(['horarios' => [self::bloque(6, '13:00', '15:00')]]))->assertSessionHasNoErrors();
        $this->assertSame(['Sábado: 13:00–15:00'], app(HorariosCurso::class)->lineas($curso->fresh()));
        $this->assertSame($camposAntes, array_intersect_key($curso->fresh()->getRawOriginal(), $this->datos()));
        // Un cliente anterior que omite el campo no debe borrar horarios existentes.
        $this->put(route('admin.cursos.update', $curso), $this->datos())->assertSessionHasNoErrors();
        $this->assertCount(1, $curso->fresh()->horarios);
        $this->put(route('admin.cursos.update', $curso), $this->datos(['horarios_presentes' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(['Horario no configurado'], app(HorariosCurso::class)->lineas($curso->fresh()));
        foreach ($tablas as $tabla) $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson());
        $this->assertSame($archivoAntes, Storage::disk('public')->get('solicitudes/historico.pdf'));
    }

    public function test_curso_sin_bloques_es_valido(): void
    {
        $this->post(route('admin.cursos.store'), $this->datos(['horarios_presentes' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(0, CursoHorario::count());
        $this->get(route('admin.cursos.show', Curso::firstOrFail()))->assertOk()->assertSee('Horario no configurado');
    }

    public static function invalidos(): array
    {
        return [
            'dia cero' => [[self::bloque(0)]], 'dia ocho' => [[self::bloque(8)]],
            'hora invalida' => [[self::bloque(1, '25:00')]],
            'formato corto' => [[self::bloque(1, '7:30')]],
            'hora ausente' => [[['dia_semana' => 1, 'hora_inicio' => '08:00']]],
            'dia ausente' => [[['hora_inicio' => '08:00', 'hora_fin' => '10:00']]],
            'duplicado' => [[self::bloque(), self::bloque()]],
            'solapamiento' => [[self::bloque(1, '08:00', '10:00'), self::bloque(1, '09:00', '11:00')]],
            'contenido' => [[self::bloque(1, '07:00', '12:00'), self::bloque(1, '08:00', '09:00')]],
            'medianoche' => [[self::bloque(1, '22:00', '02:00')]],
            'horas iguales' => [[self::bloque(1, '08:00', '08:00')]],
            'curso inyectado' => [[self::bloque() + ['curso_id' => 123]]],
            'no lista' => ['texto'], 'hora como array' => [[['dia_semana' => 1, 'hora_inicio' => [], 'hora_fin' => '10:00']]],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rechazar_bloques_invalidos_sin_crear_curso(mixed $horarios): void
    {
        $this->from(route('admin.cursos'))->post(route('admin.cursos.store'), $this->datos(['horarios' => $horarios]))->assertSessionHasErrors();
        $this->assertSame(0, Curso::count());
        $this->assertSame(0, CursoHorario::count());
    }

    public function test_bloques_iguales_en_dias_distintos_son_validos(): void
    {
        $this->post(route('admin.cursos.store'), $this->datos(['horarios' => [self::bloque(1), self::bloque(2)]]))->assertSessionHasNoErrors();
        $this->assertSame(2, CursoHorario::count());
    }

    public function test_error_al_guardar_revierte_curso_y_horarios_en_creacion_y_edicion(): void
    {
        $curso = Curso::factory()->create($this->datos());
        $curso->horarios()->create(self::bloque());
        $antes = $curso->fresh()->getRawOriginal();
        $horariosAntes = $curso->horarios->toJson();
        CursoHorario::creating(fn () => throw new \RuntimeException('Fallo ficticio de almacenamiento'));
        $this->withoutExceptionHandling();
        try {
            foreach (['post', 'put'] as $metodo) {
                try {
                    $url = $metodo === 'post' ? route('admin.cursos.store') : route('admin.cursos.update', $curso);
                    $this->$metodo($url, $this->datos(['nombre' => 'No debe persistir', 'horarios' => [self::bloque(5)]]));
                    $this->fail('Debió fallar el guardado ficticio.');
                } catch (\RuntimeException $e) {
                    $this->assertSame('Fallo ficticio de almacenamiento', $e->getMessage());
                }
                $this->assertSame(1, Curso::count());
                $this->assertSame($antes, $curso->fresh()->getRawOriginal());
                $this->assertSame($horariosAntes, $curso->fresh()->horarios->toJson());
            }
        } finally {
            CursoHorario::flushEventListeners();
        }
    }

    public function test_no_se_pueden_editar_horarios_de_curso_finalizado(): void
    {
        $curso = Curso::factory()->create($this->datos(['finalizado_manualmente' => true]));
        $curso->horarios()->create(self::bloque());
        $antes = $curso->horarios->toJson();
        $this->put(route('admin.cursos.update', $curso), $this->datos(['horarios' => [self::bloque(6)]]))->assertSessionHas('error');
        $this->assertSame($antes, $curso->fresh()->horarios->toJson());
        $this->get(route('admin.cursos.edit', $curso))->assertOk()->assertSee('sus horarios no pueden modificarse');
    }

    public function test_solo_administrador_puede_administrar_horarios(): void
    {
        $curso = Curso::factory()->create($this->datos());
        foreach (['alumno', 'docente'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('admin.cursos.store'), $this->datos(['horarios' => [self::bloque()]]))->assertForbidden();
            $this->put(route('admin.cursos.update', $curso), $this->datos(['horarios' => [self::bloque()]]))->assertForbidden();
            $this->get(route('admin.cursos.edit', $curso))->assertForbidden();
        }
        $this->assertSame(0, CursoHorario::count());
    }

    public function test_portal_usa_solo_horario_del_curso_vigente_y_ordena_bloques(): void
    {
        $user = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create(['user_id' => $user->id]);
        $curso = Curso::factory()->create($this->datos());
        $otro = Curso::factory()->create();
        $curso->horarios()->createMany([self::bloque(5), self::bloque(1)]);
        $otro->horarios()->create(self::bloque(7, '18:00', '20:00'));
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $otro->id, 'estado' => 'inactiva']);
        $respuesta = $this->actingAs($user)->get(route('alumno.dashboard', ['curso_id' => $otro->id]))->assertOk()
            ->assertSeeInOrder(['Lunes: 07:30–09:30', 'Viernes: 07:30–09:30'])->assertDontSee('Domingo: 18:00–20:00');
        $this->exportar('portal', $respuesta->getContent());
        $curso->horarios()->delete();
        $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Horario no configurado');
    }

    public function test_codigo_funciona_con_migracion_pendiente_y_no_inventa_horarios(): void
    {
        Schema::drop('curso_horarios'); // Exclusivamente SQLite en memoria, verificado antes de RefreshDatabase.
        $curso = Curso::factory()->create($this->datos());
        $this->get(route('admin.cursos.edit', $curso))->assertOk()->assertSee('migración pendiente');
        $this->get(route('admin.cursos.show', $curso))->assertOk()->assertSee('Horario no configurado');
        $this->put(route('admin.cursos.update', $curso), $this->datos(['horarios' => [self::bloque()]]))->assertSessionHasErrors('horarios');
        $this->assertSame(['Horario no configurado'], app(HorariosCurso::class)->lineas($curso));
    }

    public static function edades(): array { return [[true], [false]]; }

    #[DataProvider('edades')]
    public function test_pdf_ficticio_de_adulto_y_menor_usa_horarios_y_conserva_firmas(bool $mayor): void
    {
        $curso = Curso::factory()->create($this->datos());
        $curso->horarios()->createMany([self::bloque(6, '08:00', '12:00'), self::bloque(2, '14:00', '16:00')]);
        $firma = $this->firmaFicticia();
        $datos = ['curso' => $curso, 'cursosDisponibles' => collect([$curso]), 'fecha' => now(),
            'alumno' => new Alumno(['nombre' => 'Alumno Ficticio', 'apellido' => 'Prueba de Horarios', 'codigo' => 'PRUEBA-HORARIO', 'fecha_nacimiento' => $mayor ? '2000-01-01' : '2012-01-01', 'dui' => '00000000-0']),
            'encargado_nombre' => 'Responsable Ficticio', 'encargado_apellido' => 'Prueba', 'encargado_parentesco' => 'Madre', 'encargado_telefono' => null, 'encargado_dui' => '11111111-1',
            'firma_alumno' => $firma, 'firma_encargado' => $firma];
        $html = view('alumno.pdf.solicitud-inscripcion', $datos)->render();
        $this->assertStringContainsString('Martes: 14:00–16:00', $html);
        $this->assertStringContainsString('Sábado: 08:00–12:00', $html);
        $this->assertStringNotContainsString('7:30 a.m.', $html);
        $this->assertSame($mayor ? 1 : 2, substr_count($html, 'src="'.$firma.'"'));
        if (! $mayor) $this->assertStringContainsString('Responsable Ficticio Prueba', $html);
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        if ($dir = getenv('ITM_HORARIOS_PREVIEW_DIR')) file_put_contents($dir.'/solicitud-'.($mayor ? 'adulto' : 'menor').'.pdf', $bytes);
    }

    private function firmaFicticia(): string
    {
        $img = imagecreatetruecolor(320, 90);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagestring($img, 5, 50, 35, 'FIRMA FICTICIA', imagecolorallocate($img, 26, 58, 107));
        ob_start(); imagepng($img); $png = ob_get_clean();
        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function exportar(string $nombre, string $html): void
    {
        if (! $dir = getenv('ITM_HORARIOS_PREVIEW_DIR')) return;
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/css/app.css']['file']));
        $html = preg_replace('/<link[^>]+https:\/\/fonts\.bunny\.net[^>]*>/', '', $html);
        $html = str_replace('http://localhost/images/logo_itm.jpg', 'file:///C:/wamp64/www/ITM/public/images/logo_itm.jpg', $html);
        if (str_contains($html, 'id="portal-alumno"')) {
            $html = str_replace('</head>', '<style>'.file_get_contents(resource_path('css/alumno/portal.css')).'</style></head>', $html);
            $portalJs = preg_replace('/^import .*;\s*/m', '', file_get_contents(resource_path('js/alumno/portal.js')));
            $html = str_replace('</body>', '<script>'.$portalJs.'</script></body>', $html);
        }
        $html = str_replace('</head>', '<link rel="stylesheet" href="'.$css.'"><style>'.file_get_contents(resource_path('css/admin/horarios-curso.css')).'</style></head>', $html);
        $js = preg_replace('/^import .*;\s*/m', '', file_get_contents(resource_path('js/admin/cursos.js'))."\n".file_get_contents(resource_path('js/admin/horarios-curso.js')));
        $html = str_replace('</body>', '<script>'.$js.'</script></body>', $html);
        file_put_contents($dir.'/'.$nombre.'.html', $html);
    }
}
