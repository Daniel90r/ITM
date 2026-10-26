<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Correspondencias explícitas para boletas nuevas; nunca modifica los registros. */
final class BoletaConductaPresentacion
{
    public const REFERENCIA = [
        'Responsabilidad' => ['Microsoft Windows', 'Microsoft Word', 'Microsoft Excel'],
        'Relaciones personales' => ['Internet', 'Microsoft PowerPoint', 'Microsoft Publisher'],
        'Iniciativa y confianza en sí mismo' => ['Microsoft Access', 'CorelDraw', 'Photoshop'],
        'Hábitos de estudio y trabajo' => ['HTML', 'Macromedia Dreamweaver', 'Mantenimiento de PC', 'Redes'],
    ];

    public static function bloques(Collection $notas): array
    {
        // No asociar un programa desconocido a la referencia por cantidad o posición.
        $normalizar = static fn (string $nombre): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nombre)));
        $porNombre = $notas->groupBy(fn ($nota) => $normalizar($nota->detalleCurso?->materia?->nombre ?? ''));
        $esperadas = collect(self::REFERENCIA)->flatten();
        $cursoIds = $notas->map(fn ($nota) => $nota->detalleCurso?->curso_id)->unique();
        // La correspondencia de una materia no acredita el programa completo.
        // Admitir subconjuntos reales; ante ambigüedad no elegir un registro arbitrario.
        $correspondenciaSegura = $cursoIds->count() === 1 && $cursoIds->first() !== null
            && $esperadas->every(fn ($nombre) => ($porNombre[$normalizar($nombre)] ?? collect())->count() <= 1);

        if (! $correspondenciaSegura) {
            return $notas->map(fn ($nota) => ['aspecto' => 'Sin agrupación definida', 'notas' => collect([$nota]), 'referencia' => false, 'concepto' => 'Sin agrupación definida'])->all();
        }

        $bloques = [];
        foreach (self::REFERENCIA as $aspecto => $nombres) {
            $existentes = collect($nombres)->map(fn ($nombre) => ($porNombre[$normalizar($nombre)] ?? collect())->first())->filter()->values();
            if ($existentes->isNotEmpty()) {
                $bloques[] = ['aspecto' => $aspecto, 'notas' => $existentes, 'referencia' => true, 'concepto' => Conducta::consolidar($existentes->pluck('conducta'))];
            }
        }
        // Inglés y cualquier otra materia conservan sus registros y no desplazan los grupos.
        $claves = $esperadas->map($normalizar);
        foreach ($notas as $nota) {
            if (! $claves->contains($normalizar($nota->detalleCurso?->materia?->nombre ?? ''))) {
                $bloques[] = ['aspecto' => 'Sin agrupación definida', 'notas' => collect([$nota]), 'referencia' => false, 'concepto' => 'Sin agrupación definida'];
            }
        }

        return $bloques;
    }
}
