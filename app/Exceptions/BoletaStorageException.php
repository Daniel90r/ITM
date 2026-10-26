<?php

namespace App\Exceptions;

/** Fallo esperado del almacenamiento; no representa errores académicos ni de programación. */
class BoletaStorageException extends \RuntimeException
{
    public const MENSAJE_USUARIO = 'No se pudo emitir la boleta porque ocurrió un problema al guardar el documento oficial. No se ha completado la emisión. Contacte al administrador del sistema para revisar el incidente.';

    public function __construct(public readonly string $operacion, string $diagnostico, ?\Throwable $previous = null)
    {
        parent::__construct($diagnostico, 0, $previous);
    }
}
