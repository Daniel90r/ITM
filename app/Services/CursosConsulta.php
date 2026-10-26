<?php

namespace App\Services;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Alcance de lectura. Nunca se utiliza para inscribir, asignar o generar cuotas. */
class CursosConsulta
{
    public function catalogo(): Collection
    {
        // Cache únicamente durante esta solicitud; el accesor sigue siendo la fuente de verdad.
        $request = request();
        if (!$request->attributes->has('catalogo_cursos_consulta')) {
            $request->attributes->set('catalogo_cursos_consulta', Curso::orderBy('nombre')->orderBy('id')->get());
        }
        return $request->attributes->get('catalogo_cursos_consulta');
    }

    public function normalizar(array $f): array
    {
        $historicos = filter_var($f['incluir_historicos'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!empty($f['curso_id'])) {
            $curso = $this->catalogo()->firstWhere('id', (int) $f['curso_id']);
            if ($curso?->estado_curso === Curso::ESTADO_FINALIZADO) $historicos = true;
            if ($curso && !in_array($curso->estado_curso, [Curso::ESTADO_ACTIVO, Curso::ESTADO_FINALIZADO], true)) {
                throw ValidationException::withMessages(['curso_id' => 'Selecciona un curso activo o incluye un curso histórico finalizado.']);
            }
        }
        $f['incluir_historicos'] = (int) $historicos;
        return $f;
    }

    public function opciones(array $f): Collection
    {
        $f = $this->normalizar($f);
        return $this->catalogo()->filter(fn ($c) => $c->estado_curso === Curso::ESTADO_ACTIVO
            || ($f['incluir_historicos'] && $c->estado_curso === Curso::ESTADO_FINALIZADO))->values();
    }

    public function aplicar($query, array $f, string $columna = 'curso_id')
    {
        // Los consumidores internos anteriores conservan su alcance si no solicitan este filtro.
        if (array_key_exists('incluir_historicos', $f)) $query->whereIn($columna, $this->opciones($f)->pluck('id'));
        return $query;
    }
}
