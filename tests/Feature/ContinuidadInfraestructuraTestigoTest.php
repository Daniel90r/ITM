<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\Support\ModeloContinuidadTestigo;
use Tests\TestCase;

class ContinuidadInfraestructuraTestigoTest extends TestCase
{
    private function falla(string $codigo, callable $accion): void
    {
        try { $accion(); $this->fail('Debe cerrar sin conceder otro uso.'); }
        catch (RuntimeException $e) { $this->assertSame($codigo, $e->getMessage()); }
    }

    public function test_consulta_no_modifica_consumos_y_reinicio_no_rehabilita_id(): void
    {
        $m = new ModeloContinuidadTestigo(); $copia = $m->copiaReplica();
        $this->assertSame('libre', $m->consultar('id-ficticio'));
        $this->assertSame($copia, $m->copiaReplica());
        $m->consumir('id-ficticio', hash('sha256', 'permiso-ficticio'));
        $reiniciado = clone $m; // Mismos almacenes independientes; cliente recreado.
        $this->assertSame('consumida', $reiniciado->consultar('id-ficticio'));
        $this->falla('AUTORIZACION_CONSUMIDA', fn () => $reiniciado->consumir('id-ficticio', hash('sha256', 'otro-permiso')));
    }

    public function test_restauracion_antigua_del_testigo_no_puede_responder_libre(): void
    {
        $m = new ModeloContinuidadTestigo(); $vieja = $m->copiaReplica();
        $m->consumir('id-ficticio', hash('sha256', 'permiso-ficticio')); $m->restaurarReplica($vieja);
        $this->falla('CONTINUIDAD_PERDIDA', fn () => $m->consultar('id-ficticio'));
        $this->falla('CONTINUIDAD_PERDIDA', fn () => $m->consumir('id-ficticio', 'otro'));
    }

    public function test_reserva_independiente_sin_commit_local_cierra_todo_el_registro(): void
    {
        $m = new ModeloContinuidadTestigo();
        $this->falla('CONSUMO_REMOTO_INCIERTO', fn () => $m->consumir('id-ficticio', 'permiso-ficticio', true));
        $this->falla('CONTINUIDAD_PERDIDA', fn () => $m->consultar('otro-id'));
    }

    public function test_recibo_eliminado_aunque_el_id_exista_se_detecta(): void
    {
        $m = new ModeloContinuidadTestigo(); $m->consumir('id-ficticio', 'permiso-ficticio');
        $m->borrarReciboReplica('id-ficticio');
        $this->falla('CONTINUIDAD_PERDIDA', fn () => $m->consultar('id-ficticio'));
    }

    public function test_ancla_caida_o_respuesta_antigua_no_se_aceptan(): void
    {
        $m = new ModeloContinuidadTestigo(); $m->continuidad(); $m->anclaDisponible = false;
        $this->falla('CONTINUIDAD_NO_VERIFICABLE', fn () => $m->consultar('id-ficticio'));
        $m->anclaDisponible = true; $m->repetirAtestacion = true;
        $this->falla('ATESTACION_NO_FRESCA', fn () => $m->consultar('id-ficticio'));
    }

    public function test_rotacion_requiere_pin_aprobado_y_no_borra_consumos(): void
    {
        $m = new ModeloContinuidadTestigo(); $m->consumir('id-ficticio', 'permiso-ficticio');
        $huella = $m->rotarClaveAncla();
        $this->falla('IDENTIDAD_ANCLA_NO_VERIFICADA', fn () => $m->consultar('id-ficticio'));
        $this->falla('PIN_NO_APROBADO', fn () => $m->aprobarPinDePrueba(str_repeat('0', 64)));
        $m->aprobarPinDePrueba($huella);
        $this->assertSame('consumida', $m->consultar('id-ficticio'));
        $this->falla('AUTORIZACION_CONSUMIDA', fn () => $m->consumir('id-ficticio', 'permiso-ficticio'));
        $m->restaurarClaveAnteriorParaCaracterizacion();
        $this->falla('IDENTIDAD_ANCLA_NO_VERIFICADA', fn () => $m->consultar('id-ficticio'));
    }

    public function test_limite_si_se_restauran_todos_los_estados_no_hay_prueba_externa_de_perdida(): void
    {
        $m = new ModeloContinuidadTestigo(); $vieja = $m->copiaReplica();
        $m->consumir('id-ficticio', 'permiso-ficticio'); $m->restaurarReplica($vieja);
        $this->falla('CONTINUIDAD_PERDIDA', fn () => $m->consultar('id-ficticio'));
        $m->restaurarTambienAnclaParaCaracterizacion($vieja);
        // Contraejemplo deliberado: restaurar también la autoridad elimina la garantía.
        // Producción debe impedirlo fuera de esta frontera; NO aceptar este modelo como solución.
        $this->assertSame('libre', $m->consultar('id-ficticio'));
    }
}
