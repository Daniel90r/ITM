<?php

namespace App\Exceptions;

class BoletaEmissionException extends \Exception
{
    public static function inscripcionNoAprobada(): self
    {
        return new self('El alumno no tiene una inscripción aprobada en este curso.');
    }

    public static function sinMaterias(): self
    {
        return new self('No hay materias asignadas al alumno en este curso.');
    }

    public static function calificacionesPendientes(array $materias): self
    {
        $lista = implode(', ', array_unique($materias));
        return new self("No se puede emitir la boleta porque faltan calificaciones en: {$lista}.");
    }

    public static function sinDirectorVigente(): self
    {
        return new self('No existe un Director vigente para la fecha de emisión.');
    }

    public static function multiplesDirectores(): self
    {
        return new self('Existen múltiples Directores vigentes para la fecha de emisión. Corrija los períodos.');
    }

    public static function directorSinFirma(): self
    {
        return new self('El Director vigente no tiene una firma válida registrada.');
    }

    public static function yaEmitida(): self
    {
        return new self('Esta boleta ya fue emitida.');
    }

    public static function errorCopiandoFirma(): self
    {
        return new self('Error al copiar la firma del Director. La boleta no se ha emitido.');
    }
}