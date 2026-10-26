<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, DetalleCurso, Materia, Nota};
use App\Support\BoletaConductaPresentacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoletaEstructuraConductaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::listen(static function () { throw new \RuntimeException('Esta prueba no debe consultar ni escribir datos.'); });
    }

    public static function notasSinteticas(?array $nombres = null): Collection
    {
        $nombres ??= collect(BoletaConductaPresentacion::REFERENCIA)->flatten()->all();
        return collect($nombres)->map(function ($nombre, $i) {
            $materia = new Materia(['nombre' => $nombre, 'codigo' => 'PRUEBA-'.$i, 'es_opcional' => $nombre === 'Inglés']);
            $detalle = new DetalleCurso(['curso_id' => 999, 'materia_id' => $i + 1]);
            $detalle->setRelation('materia', $materia);
            $nota = new Nota(['detalle_curso_id' => $i + 1, 'promedio' => 8, 'conducta' => ['E', 'MB', 'B', 'P', null][$i % 5]]);
            $nota->setRelation('detalleCurso', $detalle);
            return $nota;
        });
    }

    public static function datos(Collection $notas): array
    {
        return [
            'alumno' => (new Alumno(['nombre' => 'Alumno sintético', 'apellido' => 'SIN VALIDEZ', 'codigo' => 'PRUEBA']))->setRelation('notas', $notas),
            'curso' => new Curso(['nombre' => 'Programa de referencia sintético']),
            'notas' => $notas, 'promedio_general' => 8, 'firmaDirector' => null, 'boletaEmitida' => null, 'fechaBoleta' => now(), 'calificacionesCompletas' => true, 'estado_academico' => 'Aprobado',
        ];
    }

    public function test_agrupa_por_nombre_y_no_por_posicion(): void
    {
        $bloques = BoletaConductaPresentacion::bloques(self::notasSinteticas()->reverse()->values());
        $this->assertSame([3, 3, 3, 4], array_map(fn ($b) => $b['notas']->count(), $bloques));
        foreach ($bloques as $bloque) {
            $this->assertSame(BoletaConductaPresentacion::REFERENCIA[$bloque['aspecto']], $bloque['notas']->map(fn ($n) => $n->detalleCurso->materia->nombre)->all());
        }
    }

    public function test_ingles_no_desplaza_grupos_y_no_se_interpreta_conducta(): void
    {
        $notas = self::notasSinteticas([...collect(BoletaConductaPresentacion::REFERENCIA)->flatten(), 'Inglés']);
        $antes = $notas->map->getAttributes()->all();
        $bloques = BoletaConductaPresentacion::bloques($notas->reverse()->values());
        $this->assertSame([3, 3, 3, 4, 1], array_map(fn ($b) => $b['notas']->count(), $bloques));
        $this->assertFalse($bloques[4]['referencia']);
        $this->assertSame('Inglés', $bloques[4]['notas']->first()->detalleCurso->materia->nombre);
        $this->assertSame($antes, $notas->map->getAttributes()->all());
    }

    public function test_produccion_conserva_conductas_individuales_sin_aplicar_perfil_no_confirmado(): void
    {
        $notas = self::notasSinteticas();
        $html = view('admin.pdf.boleta', self::datos($notas))->render();
        $this->assertSame(4, substr_count($html, 'class="td-concepto-conducta"'));
        $this->assertStringContainsString('>Sin evaluación válida</strong>', $html);
        $this->assertStringContainsString('>Pendiente de evaluación</td>', $html);
        $this->assertStringContainsString('Aspectos de conducta', $html);
    }

    public function test_muestra_combina_ambas_columnas_y_preserva_originales(): void
    {
        $html = view('admin.pdf.boleta-prueba', self::datos(self::notasSinteticas()))->render();
        $dom = new \DOMDocument(); @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (['td-conducta', 'td-concepto-conducta'] as $clase) {
            $spans = [];
            foreach ($xpath->query('//td[@class="'.$clase.'"]') as $celda) $spans[] = (int) $celda->getAttribute('rowspan');
            $this->assertSame([3, 3, 3, 4], $spans);
        }
        $this->assertSame(4, substr_count($html, 'class="td-concepto-conducta"'));
        $this->assertStringNotContainsString('>Por definir</td>', $html);
        $this->assertStringContainsString('Conducta por materia', $html);
        $this->assertStringContainsString('>Sin evaluación válida</strong>', $html);
        $this->assertStringContainsString('Pendiente de evaluación', $html);
        $this->assertStringContainsString('>8</td>', $html);
        $this->assertStringContainsString('>MB</td>', $html);
        $this->assertStringContainsString('Aprobado', $html);
    }

    public function test_curso_distinto_incompleto_o_ambiguo_no_recibe_aspectos_inventados(): void
    {
        foreach ([['Inglés', 'Contabilidad'], [...collect(BoletaConductaPresentacion::REFERENCIA)->flatten(), 'Microsoft Word']] as $nombres) {
            $bloques = BoletaConductaPresentacion::bloques(self::notasSinteticas($nombres));
            $this->assertCount(count($nombres), $bloques);
            $this->assertEmpty(array_filter($bloques, fn ($b) => $b['referencia']));
        }
    }

    public function test_sin_notas_no_inventa_trece_materias(): void
    {
        $html = view('admin.pdf.boleta', self::datos(collect()))->render();
        $this->assertStringContainsString('No hay calificaciones registradas', $html);
        $this->assertStringNotContainsString('Microsoft Windows', $html);
        $this->assertStringNotContainsString('Redes', $html);
    }

    public function test_no_mezcla_cursos_aunque_coincidan_nombres(): void
    {
        $notas = self::notasSinteticas();
        $notas->last()->detalleCurso->curso_id = 1000;
        $this->assertEmpty(array_filter(BoletaConductaPresentacion::bloques($notas), fn ($b) => $b['referencia']));
    }

    public function test_produccion_windows_word_combina_dos_filas_y_conserva_mb_p(): void
    {
        $notas = self::notasSinteticas(['Microsoft Word', 'Microsoft Windows']);
        $notas[0]->conducta = 'P';
        $notas[1]->conducta = 'MB';
        $html = view('admin.pdf.boleta', self::datos($notas))->render();
        $dom = new \DOMDocument(); @$dom->loadHTML($html);
        $x = new \DOMXPath($dom);
        $this->assertSame(5, $x->query('//table[@class="tabla-principal"]/thead/tr/th')->length);
        $this->assertSame(0, $x->query('//table[@class="tabla-principal"]//*[contains(text(), "Aprobado")]')->length);
        $this->assertStringNotContainsString('Docencia actual:', $html);
        $this->assertStringNotContainsString('Sin consolidación', $html);
        foreach (['td-conducta', 'td-concepto-conducta'] as $clase) {
            $this->assertSame('2', $x->query('//td[@class="'.$clase.'"]')->item(0)->getAttribute('rowspan'));
        }
        $this->assertStringContainsString('Responsabilidad', $html);
        $this->assertStringNotContainsString('Microsoft Excel', $html);
        $this->assertStringContainsString('>Muy Bueno</strong>', $html);
        $this->assertStringContainsString('>Sin evaluación válida</strong>', $html);
    }

    public function test_cinco_columnas_y_colspan_correctos_en_todos_los_casos(): void
    {
        foreach ([[], ['Microsoft Windows', 'Microsoft Word'], ['Microsoft Windows', 'Microsoft Word', 'Inglés'], ['Contabilidad'], collect(BoletaConductaPresentacion::REFERENCIA)->flatten()->all()] as $nombres) {
            $notas = self::notasSinteticas($nombres);
            $html = view('admin.pdf.boleta', self::datos($notas))->render();
            $dom = new \DOMDocument(); @$dom->loadHTML($html);
            $x = new \DOMXPath($dom);
            $this->assertSame(5, $x->query('//table[@class="tabla-principal"]/thead/tr/th')->length);
            $total = 0;
            foreach ($x->query('//table[@class="tabla-principal"]/tbody[last()]/tr[last()]/td') as $td) $total += (int) ($td->getAttribute('colspan') ?: 1);
            $this->assertSame(5, $total);
            $this->assertStringNotContainsString('Conducta registrada</th>', $html);
        }
    }

    public function test_firmante_solo_muestra_nombre_historico_disponible(): void
    {
        $datos = self::datos(self::notasSinteticas(['Microsoft Windows', 'Microsoft Word']));
        foreach ([null, new \App\Models\Boleta(['director_nombre' => '']), new \App\Models\Boleta(['director_nombre' => 'Directora Histórica Sintética'])] as $boleta) {
            $datos['boletaEmitida'] = $boleta;
            $html = view('admin.pdf.boleta', $datos)->render();
            $this->assertStringNotContainsString('Sin boleta emitida', $html);
            $this->assertStringNotContainsString('Firma disponible al emitir', $html);
            $this->assertStringContainsString('class="firma-linea"', $html);
            $this->assertStringContainsString('class="firma-cargo">Director</div>', $html);
            $this->assertStringContainsString('class="sello"', $html);
            if ($boleta?->director_nombre) {
                $this->assertStringContainsString('Directora Histórica Sintética', $html);
            } else {
                $this->assertStringNotContainsString('class="firma-nombre"', $html);
            }
        }
    }
}
