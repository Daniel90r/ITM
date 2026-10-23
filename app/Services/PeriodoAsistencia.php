<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;

class PeriodoAsistencia
{
    public function validar(Request $request): array
    {
        $f = $request->validate([
            'modalidad' => 'nullable|in:mensual,anual',
            'anio' => 'required_with:modalidad|nullable|integer|between:1900,2100',
            'mes' => 'required_if:modalidad,mensual|nullable|integer|between:1,12',
        ]);
        if (!empty($f['modalidad']) && ($request->filled('fecha_desde') || $request->filled('fecha_hasta'))) {
            throw ValidationException::withMessages(['modalidad' => 'No combines mes o año con Desde/Hasta. Selecciona una modalidad o utiliza el intervalo anterior.']);
        }
        if (($f['modalidad'] ?? '') !== 'mensual' && $request->filled('mes')) {
            throw ValidationException::withMessages(['mes' => 'El mes solo corresponde a la modalidad mensual.']);
        }
        if (empty($f['modalidad']) && $request->filled('anio')) {
            throw ValidationException::withMessages(['anio' => 'Selecciona Mensual o Anual para utilizar el año.']);
        }
        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    public function conFechas(array $f): array
    {
        if (empty($f['modalidad'])) return $f;
        $inicio = CarbonImmutable::create((int) $f['anio'], $f['modalidad'] === 'mensual' ? (int) $f['mes'] : 1, 1)->startOfDay();
        return $f + ['fecha_desde' => $inicio->toDateString(), 'fecha_hasta' => ($f['modalidad'] === 'mensual' ? $inicio->endOfMonth() : $inicio->endOfYear())->toDateString()];
    }
}
