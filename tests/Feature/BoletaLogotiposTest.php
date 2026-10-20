<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\DetalleCurso;
use App\Models\Materia;
use App\Models\Nota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoletaLogotiposTest extends TestCase
{
    public function test_logotipos_locales_se_incorporan_completos_al_html(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $html = view('admin.pdf.boleta', $this->datosFicticios())->render();
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $images = (new \DOMXPath($document))->query('//img[@class="header-logo"]');
        $this->assertCount(2, $images);

        foreach (['logo_itm.jpg' => 'image/jpeg', 'escudo_el_salvador.png' => 'image/png'] as $file => $mime) {
            $image = $images->item($file === 'logo_itm.jpg' ? 0 : 1);
            $prefix = 'data:'.$mime.';base64,';
            $this->assertStringStartsWith($prefix, $image->getAttribute('src'));
            $bytes = base64_decode(substr($image->getAttribute('src'), strlen($prefix)), true);
            $this->assertSame(file_get_contents(public_path('images/'.$file)), $bytes);
            $this->assertSame($mime, getimagesizefromstring($bytes)['mime']);
        }
        $this->assertStringNotContainsString('logo-circle', $html);
        $this->assertStringContainsString('.header-logo { width:64px; height:auto;', $html);
    }

    public function test_pdf_ficticio_con_logotipos_conserva_una_pagina(): void
    {
        foreach ([false, true] as $conNotas) {
            $pdf = Pdf::loadView('admin.pdf.boleta', $this->datosFicticios($conNotas))->setPaper('a4', 'portrait');
            $bytes = $pdf->output();
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
            // Exportación opcional, solo de datos ficticios, para inspección visual local.
            if ($conNotas && getenv('ITM_LOGOS_PDF_PREVIEW')) {
                file_put_contents(getenv('ITM_LOGOS_PDF_PREVIEW'), $bytes);
            }
        }
    }

    private function datosFicticios(bool $conNotas = false): array
    {
        $notas = collect();
        if ($conNotas) {
            for ($i = 1; $i <= 13; $i++) {
                $detalle = new DetalleCurso();
                $detalle->setRelation('materia', new Materia(['nombre' => 'Programa de prueba '.$i]));
                $nota = new Nota(['detalle_curso_id' => $i, 'promedio' => 9, 'conducta' => 'E']);
                $nota->setRelation('detalleCurso', $detalle);
                $notas->push($nota);
            }
        }

        return [
            'alumno' => new Alumno(['nombre' => 'Alumno Ficticio', 'apellido' => 'Prueba de Logotipos']),
            'notas' => $notas,
            'promedio_general' => $conNotas ? 9 : null,
            'fechaBoleta' => \Carbon\Carbon::parse('2026-10-18'),
            'boletaEmitida' => null,
            'firmaDirector' => null,
        ];
    }
}
