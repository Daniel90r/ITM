<?php

namespace App\Console\Commands;

use App\Services\ControlTransicionDocumental;
use Illuminate\Console\Command;
use Throwable;

class BloqueoDocumental extends Command
{
    protected $signature = 'inscripciones:bloqueo-documental {--activar} {--retirar} {--confirmar=}';
    protected $description = 'Consulta la barrera; cambios permitidos únicamente en pruebas aisladas';

    public function handle(ControlTransicionDocumental $control): int
    {
        try {
            if (!$this->option('activar') && !$this->option('retirar')) {
                $this->line(json_encode(['barrera_habilitada' => (bool) config('documentos_inscripcion.transition_guard_enabled'),
                    'bloqueado' => $control->bloqueado()], JSON_THROW_ON_ERROR));
                return self::SUCCESS;
            }
            if ($this->option('activar') && $this->option('retirar') || $this->option('confirmar') !== 'CAMBIAR_BLOQUEO_DOCUMENTAL') {
                $this->error('CONFIRMACION_DE_BLOQUEO_REQUERIDA'); return self::FAILURE;
            }
            $control->exclusiva(function () use ($control) {
                if ($this->option('activar')) $control->congelar(); else $control->retirar();
            });
            $this->info('Estado del bloqueo actualizado únicamente en el entorno aislado.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('BLOQUEO_NO_MODIFICADO'); return self::FAILURE;
        }
    }
}
