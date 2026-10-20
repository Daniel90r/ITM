<?php

namespace App\Console\Commands;

use App\Services\TransicionDocumental;
use Illuminate\Console\Command;
use Throwable;

class TransicionarDocumentosInscripcion extends Command
{
    protected $signature = 'inscripciones:transicionar-documentos
        {--ejecutar : Ejecución explícita; aislada salvo canal real autorizado}
        {--real : Canal MySQL local con permiso firmado de uso único}
        {--autorizacion= : Sobre firmado externo}
        {--autorizacion-sha256= : Huella aprobada del sobre}
        {--clave-sha256= : Huella aprobada de la clave pública externa}
        {--confirmar= : INCORPORAR_DOCUMENTOS o INCORPORAR_DOCUMENTOS_REALES}
        {--inventario= : JSON privado previamente revisado}
        {--sha256= : Huella autorizada del JSON}
        {--cuarentena= : Raíz privada externa}
        {--lote= : Identificador estable de la operación}';
    protected $description = 'Simula por defecto; ejecución real exige autorización firmada externa';

    public function handle(TransicionDocumental $transicion): int
    {
        try {
            if ($this->option('real') && !$this->option('ejecutar')) {
                $this->error('EJECUCION_EXPLICITA_REQUERIDA'); return self::FAILURE;
            }
            if (!$this->option('ejecutar')) {
                $this->line(json_encode(['modo' => 'simulacion'] + $transicion->resumen($transicion->planificar()), JSON_THROW_ON_ERROR));
                return self::SUCCESS;
            }
            if ($this->option('confirmar') !== ($this->option('real') ? 'INCORPORAR_DOCUMENTOS_REALES' : 'INCORPORAR_DOCUMENTOS')) {
                $this->error('CONFIRMACION_EXPLICITA_REQUERIDA');
                return self::FAILURE;
            }
            foreach (['inventario', 'sha256', 'cuarentena', 'lote'] as $opcion) {
                if (!$this->option($opcion)) {
                    $this->error('PARAMETROS_DE_EJECUCION_INCOMPLETOS');
                    return self::FAILURE;
                }
            }
            if ($this->option('real')) {
                foreach (['autorizacion', 'autorizacion-sha256', 'clave-sha256'] as $opcion) {
                    if (!$this->option($opcion)) { $this->error('AUTORIZACION_REAL_REQUERIDA'); return self::FAILURE; }
                }
                $resumen = $transicion->ejecutarReal($this->option('inventario'), $this->option('sha256'), $this->option('cuarentena'), $this->option('lote'),
                    $this->option('autorizacion'), $this->option('autorizacion-sha256'), $this->option('clave-sha256'));
            } else {
                $resumen = $transicion->ejecutar($this->option('inventario'), $this->option('sha256'), $this->option('cuarentena'), $this->option('lote'));
            }
            $this->line(json_encode(['modo' => $this->option('real') ? 'ejecucion_autorizada' : 'ejecucion_aislada', 'bloqueo_conservado' => true] + $resumen, JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (Throwable $e) {
            // No imprimir mensajes SQL, nombres, paths ni trazas de bibliotecas.
            $codigo = preg_match('/\A[A-Z][A-Z_]{3,80}\z/D', $e->getMessage()) ? $e->getMessage() : 'TRANSICION_DETENIDA';
            $this->error($codigo.'; conserve las copias y revise el estado antes de reintentar.');
            return self::FAILURE;
        }
    }
}
