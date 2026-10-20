<?php

namespace App\Services;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Schema, Validator};
use Illuminate\Validation\ValidationException;

class HorariosCurso
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function disponible(): bool
    {
        return Schema::hasTable('curso_horarios');
    }

    /** null conserva horarios existentes para clientes anteriores; [] los limpia explícitamente. */
    public function validar(Request $request): ?array
    {
        if (! $request->exists('horarios') && ! $request->exists('horarios_presentes')) return null;
        $data = $request->all();
        $data['horarios'] = $request->input('horarios', []);
        $validator = Validator::make($data, [
            'horarios_presentes' => 'sometimes|in:1',
            'horarios' => 'array|max:70',
            'horarios.*' => 'required|array:dia_semana,hora_inicio,hora_fin',
            'horarios.*.dia_semana' => 'required|integer|between:1,7',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fin' => 'required|date_format:H:i',
        ], [
            'horarios.*.dia_semana.*' => 'Selecciona un día válido de lunes a domingo.',
            'horarios.*.hora_inicio.*' => 'Indica la hora inicial con formato HH:mm.',
            'horarios.*.hora_fin.*' => 'Indica la hora final con formato HH:mm.',
        ]);
        $validator->after(function ($validator) use ($data) {
            if ($validator->errors()->isNotEmpty()) return;
            $porDia = [];
            foreach ($data['horarios'] as $key => $bloque) {
                if ($bloque['hora_fin'] <= $bloque['hora_inicio']) {
                    $validator->errors()->add("horarios.$key.hora_fin", 'La hora final debe ser posterior a la inicial, sin cruzar medianoche.');
                }
                $porDia[(int) $bloque['dia_semana']][] = $bloque + ['indice' => $key];
            }
            foreach ($porDia as $bloques) {
                usort($bloques, fn ($a, $b) => [$a['hora_inicio'], $a['hora_fin']] <=> [$b['hora_inicio'], $b['hora_fin']]);
                $anterior = null;
                foreach ($bloques as $bloque) {
                    if ($anterior && $bloque['hora_inicio'] < $anterior['hora_fin']) {
                        $duplicado = $bloque['hora_inicio'] === $anterior['hora_inicio'] && $bloque['hora_fin'] === $anterior['hora_fin'];
                        $validator->errors()->add('horarios.'.$bloque['indice'].'.hora_inicio', $duplicado ? 'El bloque está duplicado.' : 'Los bloques de un mismo día no pueden solaparse.');
                    }
                    if (! $anterior || $bloque['hora_fin'] > $anterior['hora_fin']) $anterior = $bloque;
                }
            }
        });
        $validated = $validator->validate()['horarios'];
        if ($validated && ! $this->disponible()) {
            throw ValidationException::withMessages(['horarios' => 'Los horarios aún no están habilitados: falta aplicar la migración autorizada.']);
        }
        return array_values($validated);
    }

    /** Se llama dentro de la transacción que guarda el curso, bajo su bloqueo. */
    public function guardar(Curso $curso, ?array $bloques): void
    {
        if ($bloques === null) return;
        if (! $this->disponible()) {
            if ($bloques) throw ValidationException::withMessages(['horarios' => 'Falta aplicar la migración de horarios.']);
            return;
        }
        $curso->horarios()->delete();
        $curso->horarios()->createMany($bloques);
        $curso->unsetRelation('horarios');
    }

    public function cargar(Curso $curso): Curso
    {
        if (! $curso->relationLoaded('horarios')) {
            if ($this->disponible()) $curso->load('horarios');
            else $curso->setRelation('horarios', collect());
        }
        return $curso;
    }

    public function lineas(?Curso $curso): array
    {
        if (! $curso) return ['Horario no configurado'];
        $horarios = $this->cargar($curso)->horarios;
        if ($horarios->isEmpty()) return ['Horario no configurado'];
        return $horarios->map(fn ($h) => self::DIAS[$h->dia_semana].': '.substr($h->hora_inicio, 0, 5).'–'.substr($h->hora_fin, 0, 5))->all();
    }
}
