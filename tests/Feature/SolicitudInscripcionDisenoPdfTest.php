<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SolicitudInscripcionDisenoPdfTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'mayor-firmado' => [true, true, false, false],
            'menor-firmado' => [false, true, false, false],
            'menor-datos-largos' => [false, true, true, false],
            'formulario-sin-firmas' => [true, false, false, false],
            'varias-paginas' => [false, false, false, true],
        ];
    }

    #[DataProvider('casos')]
    public function test_documento_conserva_datos_condiciones_y_firmas(
        bool $mayor, bool $firmado, bool $largo, bool $variosCursos
    ): void {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse('2026-10-18 10:30:00'));
        $alumno = new Alumno([
            'nombre' => $largo ? str_repeat('Nombre Ficticio Compuesto ', 4) : 'Alumno Ficticio',
            'apellido' => 'Prueba de Diseño', 'codigo' => 'PRUEBA-PDF-001',
            'fecha_nacimiento' => $mayor ? '2000-01-15' : '2012-01-15',
            'telefono' => '0000-0000', 'genero' => 'F', 'dui' => '00000000-0',
            'direccion' => str_repeat('Dirección ficticia para verificar la impresión, ', $largo ? 35 : 1),
        ]);
        $curso = new Curso(['nombre' => 'Curso Ficticio de Computación', 'seccion' => 'A', 'nivel' => 'Matutino']);
        $firma = $firmado ? $this->firmaFicticia() : null;
        $datos = [
            'alumno' => $alumno, 'curso' => $variosCursos ? null : $curso,
            'cursosDisponibles' => collect(range(1, $variosCursos ? 60 : 1))->map(
                fn ($i) => new Curso(['nombre' => 'Curso Ficticio '.$i, 'seccion' => 'A', 'nivel' => 'Vespertino'])
            ),
            'fecha' => now(), 'firma_alumno' => $firma, 'firma_encargado' => $firma,
            'encargado_nombre' => 'Responsable Ficticio', 'encargado_apellido' => 'Prueba',
            'encargado_parentesco' => 'Madre', 'encargado_telefono' => null,
            'encargado_dui' => '11111111-1',
        ];
        $html = view('alumno.pdf.solicitud-inscripcion', $datos)->render();
        $this->assertStringContainsString(e($alumno->nombre_completo), $html);
        $this->assertStringContainsString(e($alumno->direccion), $html);
        $this->assertStringContainsString('PRUEBA-PDF-001', $html);
        $this->assertStringContainsString('Ciclo 2026', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/logo_itm.jpg'))), $html);
        $this->assertStringNotContainsString('escudo_el_salvador', $html);
        $this->assertStringNotContainsString('#d1fae5', $html);
        $this->assertStringContainsString($mayor ? 'DUI del alumno' : 'Responsable Ficticio Prueba', $html);
        if ($mayor) {
            $this->assertStringNotContainsString('Firma del Encargado', $html);
            $this->assertStringNotContainsString('11111111-1', $html);
        } else {
            $this->assertStringContainsString('Firma del Encargado', $html);
            $this->assertStringContainsString('11111111-1', $html);
            $this->assertStringContainsString('Madre', $html);
        }
        $this->assertSame($firmado ? ($mayor ? 1 : 2) : 0, substr_count($html, 'class="firma-img"'));
        if ($firmado) {
            $this->assertSame($mayor ? 1 : 2, substr_count($html, 'src="'.$firma.'"'));
        }
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $paginas = $pdf->getDomPDF()->getCanvas()->get_page_count();
        if ($variosCursos) {
            $this->assertGreaterThan(1, $paginas);
        } elseif (! $largo) {
            $this->assertSame(1, $paginas);
        } else {
            $this->assertLessThanOrEqual(3, $paginas);
        }
        // Solo exporta documentos ficticios a un directorio temporal indicado explícitamente.
        if ($directorio = getenv('ITM_SOLICITUD_PREVIEW_DIR')) {
            $nombre = $variosCursos ? 'varias-paginas' : ($largo ? 'datos-largos' : ($mayor ? 'mayor' : 'menor'));
            $nombre .= $firmado ? '-firmado' : '-sin-firmas';
            file_put_contents($directorio.'/'.$nombre.'.pdf', $bytes);
        }
    }

    private function firmaFicticia(): string
    {
        $image = imagecreatetruecolor(320, 90);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $tinta = imagecolorallocate($image, 26, 58, 107);
        imagestring($image, 5, 40, 35, 'FIRMA FICTICIA - PRUEBA', $tinta);
        imageline($image, 35, 63, 280, 59, $tinta);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
