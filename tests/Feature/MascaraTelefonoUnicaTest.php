<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La máscara de teléfono tiene UNA sola implementación.
 *
 * utils/inputMasks.js la aplica por delegación a [data-phone-mask] (importado
 * desde app.js), así que cubre también los modales que se crean después.
 * Duplicarla en modals.js dejaba dos fuentes de verdad y riesgo de doble
 * binding con salto de cursor. DUI sí se mantiene en modals.js porque
 * maestro.js lo inicializa explícitamente.
 */
class MascaraTelefonoUnicaTest extends TestCase
{
    private function js(string $ruta): string
    {
        return file_get_contents(base_path($ruta));
    }

    private function partial(string $ruta): string
    {
        return file_get_contents(base_path('resources/views/admin/partials/' . $ruta));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Una sola implementación
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_modals_js_no_declara_mas_la_mascara_de_telefono(): void
    {
        $js = $this->js('resources/js/admin/modals.js');

        $this->assertStringNotContainsString('function formatearTelefono', $js);
        $this->assertStringNotContainsString('inicializarFormatoTelefono', $js);
        $this->assertStringNotContainsString('formatearTelefono,', $js);
    }

    #[Test]
    public function test_input_masks_js_es_la_unica_fuente_de_la_mascara_de_telefono(): void
    {
        $inputMasks = $this->js('resources/js/utils/inputMasks.js');

        $this->assertStringContainsString('const formatearTelefono', $inputMasks);
        $this->assertStringContainsString('[data-phone-mask]', $inputMasks);

        // Solo puede existir una implementación en todo resources/js.
        $definiciones = [];
        foreach (glob(base_path('resources/js/**/*.js')) as $archivo) {
            if (str_contains(file_get_contents($archivo), 'const formatearTelefono')
                || str_contains(file_get_contents($archivo), 'function formatearTelefono')) {
                $definiciones[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivo);
            }
        }

        $this->assertEquals(
            [str_replace('\\', '/', 'resources/js/utils/inputMasks.js')],
            $definiciones,
            'Solo inputMasks.js debe definir la máscara de teléfono.'
        );
    }

    #[Test]
    public function test_input_masks_usa_delegacion_para_cubrir_modales_dinamicos(): void
    {
        $inputMasks = $this->js('resources/js/utils/inputMasks.js');

        // Delegación en document: funciona con nodos que aún no existen.
        $this->assertStringContainsString("document.addEventListener('input'", $inputMasks);
        $this->assertStringNotContainsString('addEventListener(\'input\', formatearTelefono)', $inputMasks);

        // Y está cargado en la entrada principal.
        $this->assertStringContainsString("import './utils/inputMasks'", $this->js('resources/js/app.js'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Los cuatro inputs importantes están cubiertos por data-phone-mask
    // ═══════════════════════════════════════════════════════════════════════

    public static function inputsCriticos(): array
    {
        return [
            'nuevo alumno' => ['modal-nuevo-alumno.blade.php', 'telefonoNuevoAlumno'],
            'editar alumno' => ['modal-editar-alumno.blade.php', 'editTelefono'],
            'nuevo maestro' => ['modal-nuevo-maestro.blade.php', 'telefonoNuevoMaestro'],
            'editar maestro' => ['modal-editar-maestro.blade.php', 'editTelefono'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('inputsCriticos')]
    public function test_el_input_de_telefono_critico_usa_data_phone_mask(string $archivo, string $id): void
    {
        $html = $this->partial($archivo);

        $this->assertStringContainsString('id="' . $id . '"', $html, "Falta el input #{$id} en {$archivo}");

        $posicion = strpos($html, 'id="' . $id . '"');
        $this->assertNotFalse($posicion);

        $fragmento = substr($html, $posicion, 400);

        $this->assertStringContainsString(
            'data-phone-mask',
            $fragmento,
            "El input #{$id} de {$archivo} no tiene data-phone-mask: la máscara no se aplicaría."
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DUI sigue intacto
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_la_mascara_de_dui_sigue_disponible_para_maestros(): void
    {
        $modals = $this->js('resources/js/admin/modals.js');
        $maestros = $this->js('resources/js/admin/maestros.js');

        $this->assertStringContainsString('function formatearDui', $modals);
        $this->assertStringContainsString('function inicializarFormatoDui', $modals);
        $this->assertStringContainsString('inicializarFormatoDui', $modals);
        $this->assertStringContainsString("inicializarFormatoDui('#duiNuevoMaestro, #editDui')", $maestros);
    }

    #[Test]
    public function test_los_inputs_de_dui_siguen_teniendo_su_id(): void
    {
        $this->assertStringContainsString('id="duiNuevoMaestro"', $this->partial('modal-nuevo-maestro.blade.php'));
        $this->assertStringContainsString('id="editDui"', $this->partial('modal-editar-maestro.blade.php'));
    }
}
