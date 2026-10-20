<?php

namespace App\Services;

use App\Models\Alumno;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EncargadoInscripcion
{
    /** Consulta siempre el expediente: no utiliza campos del navegador ni relaciones cacheadas. */
    public function inspeccionar(Alumno $alumno): array
    {
        if ($alumno->es_mayor_de_edad) {
            return ['datos' => null, 'error' => null];
        }

        $encargados = $alumno->encargados()->get();
        $error = match (true) {
            $encargados->isEmpty() => 'No tienes un encargado vinculado.',
            $encargados->count() !== 1 => 'Hay varios encargados vinculados y no se ha definido quién debe firmar.',
            default => null,
        };

        if ($error) {
            return ['datos' => null, 'error' => $error.' Contacta a Administración para poder enviar tu solicitud.'];
        }

        $encargado = $encargados->sole();
        $datos = [
            'encargado_id' => $encargado->id,
            'encargado_nombre' => trim((string) $encargado->nombre),
            'encargado_apellido' => trim((string) $encargado->apellido),
            'encargado_dui' => trim((string) $encargado->dui),
            'encargado_telefono' => $encargado->telefono,
            'encargado_parentesco' => trim((string) $encargado->pivot->parentesco),
        ];

        $incompleto = Validator::make($datos, [
            'encargado_nombre' => 'required|string|max:100',
            'encargado_apellido' => 'required|string|max:100',
            'encargado_dui' => ['required', 'string', 'regex:/^\d{8}-\d$/'],
            'encargado_parentesco' => 'required|string|max:50',
            // El teléfono sigue siendo opcional, igual que en el registro administrativo.
        ])->fails();

        $error = ! $encargado->activo
            ? 'El encargado vinculado está inactivo.'
            : ($incompleto ? 'Los datos obligatorios del encargado están incompletos o no son válidos.' : null);

        return [
            'datos' => $datos,
            'error' => $error ? $error.' Contacta a Administración para poder enviar tu solicitud.' : null,
        ];
    }

    public function exigir(Alumno $alumno): ?array
    {
        $resultado = $this->inspeccionar($alumno);
        if ($resultado['error']) {
            throw ValidationException::withMessages(['encargado' => $resultado['error']]);
        }

        return $resultado['datos'];
    }
}
