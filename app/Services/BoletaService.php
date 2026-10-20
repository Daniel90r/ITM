<?php

namespace App\Services;

use App\Exceptions\BoletaEmissionException;
use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\DetalleBoleta;
use App\Models\DetalleCurso;
use App\Models\Director;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Nota;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BoletaService
    {
    /**
     * Emitir una boleta histórica inmutable para un alumno en un curso.
     *
     * @param int $alumnoId
     * @param int $cursoId
     * @return Boleta
     * @throws BoletaEmissionException
     */
    public function emitir(int $alumnoId, int $cursoId): Boleta
    {
        $fechaEmision = now();

        return DB::transaction(function () use ($alumnoId, $cursoId, $fechaEmision) {
            // 1. Preparar datos académicos (compartido con previsualizar)
            $preparacion = $this->prepararAcademico($alumnoId, $cursoId);

            if ($preparacion['detalles_esperados']->isEmpty()) {
                throw BoletaEmissionException::sinMaterias();
            }

            // 2. Validar notas para cada materia esperada
            $notasValidadas = $this->validarNotas($alumnoId, $preparacion['detalles_esperados']);

            // 3. Obtener Director vigente
            $director = $this->obtenerDirectorVigente($fechaEmision);

            // 4. Validar firma del Director
            $this->validarFirmaDirector($director);

            // 5. Verificar si ya existe boleta
            if (Boleta::where('alumno_id', $alumnoId)->where('curso_id', $cursoId)->exists()) {
                throw BoletaEmissionException::yaEmitida();
            }

            // 6. Calcular promedio general y concepto
            $promedioGeneral = $this->calcularPromedioGeneral($notasValidadas);
            $conceptoGeneral = $this->calcularConceptoGeneral($promedioGeneral);

            // 7. Preparar datos de la boleta (ruta temporal para firma)
            $alumno = Alumno::findOrFail($alumnoId);
            $curso = Curso::findOrFail($cursoId);

            $rutaTemporal = $this->generarRutaTemporalFirma($director->firma_path);
            $rutaFinal = null;
            $firmaCopiada = false;

            try {
                // 8. Crear boleta con ruta temporal
                $boleta = Boleta::create([
                    'alumno_id'                   => $alumnoId,
                    'curso_id'                    => $cursoId,
                    'director_id'                 => $director->id,
                    'inscripcion_id'              => $preparacion['inscripcion']->id,
                    'fecha_emision'               => $fechaEmision,
                    'alumno_nombre'               => $alumno->nombre,
                    'alumno_apellido'             => $alumno->apellido,
                    'alumno_codigo'               => $alumno->codigo,
                    'curso_nombre'                => $curso->nombre,
                    'curso_turno'                 => $curso->nivel,
                    'curso_seccion'               => $curso->seccion,
                    'curso_anio_lectivo'          => $curso->anio_lectivo,
                    'director_nombre'             => $director->nombre,
                    'director_firma_snapshot_path'=> $rutaTemporal,
                    'promedio_general'            => $promedioGeneral,
                    'concepto_general'            => $conceptoGeneral,
                ]);

                // 9. Copiar firma a ruta histórica definitiva
                $rutaFinal = $this->generarRutaFinalFirma($boleta->id, $director->firma_path);
                $copiada = Storage::disk('local')->copy($director->firma_path, $rutaFinal);

                if (! $copiada) {
                    throw new \RuntimeException(
                        'No se pudo crear el snapshot histórico de la firma del Director.'
                    );
                }

                $firmaCopiada = true;

                // 10. Actualizar boleta con ruta final
                $boleta->update(['director_firma_snapshot_path' => $rutaFinal]);

                // 11. Crear detalles de boleta
                $this->crearDetallesBoleta($boleta->id, $notasValidadas);

                return $boleta->fresh();

            } catch (QueryException $e) {
                // Manejo específico de violación de uk_boleta_alumno_curso
                if ($this->esDuplicadoBoleta($e)) {
                    throw BoletaEmissionException::yaEmitida();
                }
                throw $e;
            } catch (\Throwable $e) {
                // Cleanup si se copió la firma pero falló algo después
                if ($firmaCopiada && $rutaFinal && Storage::disk('local')->exists($rutaFinal)) {
                    Storage::disk('local')->delete($rutaFinal);
                }
                throw $e;
            }
        });
    }

    /**
     * Previsualizar la boleta para un alumno en un curso (READ-ONLY).
     *
     * NO crea Boleta, NO crea DetalleBoleta, NO copia firma,
     * NO modifica Nota, NO modifica Inscripcion, NO modifica alumno_materia,
     * NO ejecuta transaction de escritura, NO exige Director.
     *
     * @param int $alumnoId
     * @param int $cursoId
     * @return array
     * @throws BoletaEmissionException si la inscripción no es aprobada
     */
    public function previsualizar(int $alumnoId, int $cursoId): array
    {
        // Validar inscripción aprobada (igual que en emitir)
        $inscripcion = $this->validarInscripcion($alumnoId, $cursoId);

        // Obtener materias esperadas (compartido con emitir)
        $detallesEsperados = $this->obtenerMateriasEsperadas($cursoId, $alumnoId);

        if ($detallesEsperados->isEmpty()) {
            return [
                'inscripcion'                => $inscripcion,
                'detalles_esperados'         => collect(),
                'filas'                      => [],
                'materias_esperadas'         => 0,
                'materias_completas'         => 0,
                'puede_emitir_academicamente' => false,
                'promedio_general'           => null,
                'concepto_general'           => null,
                'faltantes'                  => ['No hay materias configuradas para emitir esta boleta.'],
            ];
        }

        // Obtener notas existentes para los detalles esperados
        $detalleIds = $detallesEsperados->pluck('id')->toArray();
        $notas = Nota::where('alumno_id', $alumnoId)
            ->whereIn('detalle_curso_id', $detalleIds)
            ->get()
            ->keyBy('detalle_curso_id');

        // Verificar duplicados (mismo alumno + mismo detalle_curso)
        $conteoPorDetalle = Nota::where('alumno_id', $alumnoId)
            ->whereIn('detalle_curso_id', $detalleIds)
            ->selectRaw('detalle_curso_id, count(*) as total')
            ->groupBy('detalle_curso_id')
            ->havingRaw('count(*) > 1')
            ->pluck('detalle_curso_id')
            ->toArray();

        $filas = [];
        $materiasFaltantes = [];
        $materiasIncompletas = [];

        foreach ($detallesEsperados as $dc) {
            $nota = $notas->get($dc->id);
            $materiaNombre = $dc->materia->nombre;

            if (! $nota) {
                $materiasFaltantes[] = $materiaNombre;
                $filas[] = [
                    'detalle_curso' => $dc,
                    'materia'       => $dc->materia,
                    'nota'          => null,
                    'completa'      => false,
                    'faltantes'     => ['Sin nota registrada'],
                    'concepto'      => null,
                ];
                continue;
            }

            // Verificar los 4 componentes numéricos + promedio
            $componentes = [
                'laboratorio'    => $nota->laboratorio,
                'examen_teorico' => $nota->examen_teorico,
                'practica'       => $nota->practica,
                'promedio'       => $nota->promedio,
            ];

            $faltantes = [];
            foreach ($componentes as $nombre => $valor) {
                if ($valor === null) {
                    $faltantes[] = $nombre;
                }
            }

            $completa = empty($faltantes);

            $conceptoMateria = null;
            if ($completa) {
                $conceptoMateria = $this->calcularConceptoMateria($nota->promedio);
            }

            if (! $completa) {
                $materiasIncompletas[] = $materiaNombre;
            }

            $filas[] = [
                'detalle_curso' => $dc,
                'materia'       => $dc->materia,
                'nota'          => $nota,
                'completa'      => $completa,
                'faltantes'     => $faltantes,
                'concepto'      => $conceptoMateria,
            ];
        }

        $materiasEsperadas = $detallesEsperados->count();
        $materiasCompletas = $materiasEsperadas - count($materiasFaltantes) - count($materiasIncompletas);

        $todasFaltantes = array_merge($materiasFaltantes, $materiasIncompletas);
        $puedeEmitirAcademicamente = empty($todasFaltantes);

        $promedioGeneral = null;
        $conceptoGeneral = null;

        if ($puedeEmitirAcademicamente) {
            $notasValidadas = [];
            foreach ($filas as $fila) {
                if ($fila['completa'] && $fila['nota']) {
                    $notasValidadas[$fila['detalle_curso']->id] = [
                        'detalle_curso' => $fila['detalle_curso'],
                        'nota'          => $fila['nota'],
                    ];
                }
            }
            if (! empty($notasValidadas)) {
                $promedioGeneral = $this->calcularPromedioGeneral($notasValidadas);
                $conceptoGeneral = $this->calcularConceptoGeneral($promedioGeneral);
            }
        }

        return [
            'inscripcion'                 => $inscripcion,
            'detalles_esperados'          => $detallesEsperados,
            'filas'                       => $filas,
            'materias_esperadas'          => $materiasEsperadas,
            'materias_completas'          => $materiasCompletas,
            'puede_emitir_academicamente' => $puedeEmitirAcademicamente,
            'promedio_general'            => $promedioGeneral,
            'concepto_general'            => $conceptoGeneral,
            'faltantes'                   => $todasFaltantes,
        ];
    }

    /**
     * Preparar datos académicos compartidos entre emitir() y previsualizar().
     *
     * @return array{inscripcion: Inscripcion, detalles_esperados: Collection}
     * @throws BoletaEmissionException
     */
    private function prepararAcademico(int $alumnoId, int $cursoId): array
    {
        $inscripcion = $this->validarInscripcion($alumnoId, $cursoId);
        $detallesEsperados = $this->obtenerMateriasEsperadas($cursoId, $alumnoId);

        return [
            'inscripcion'        => $inscripcion,
            'detalles_esperados' => $detallesEsperados,
        ];
    }

    /**
     * Validar que existe inscripción aprobada.
     */
    private function validarInscripcion(int $alumnoId, int $cursoId): Inscripcion
    {
        $inscripcion = Inscripcion::where('alumno_id', $alumnoId)
            ->where('curso_id', $cursoId)
            ->where('estado', 'aprobada')
            ->first();

        if (! $inscripcion) {
            throw BoletaEmissionException::inscripcionNoAprobada();
        }

        return $inscripcion;
    }

    /**
     * Obtener los DetalleCurso que corresponden al alumno en este curso.
     *
     * Reglas:
     * - Materias NO opcionales (es_opcional = false): siempre corresponden.
     * - Materias opcionales (es_opcional = true): solo si existe alumno_materia.
     */
    private function obtenerMateriasEsperadas(int $cursoId, int $alumnoId)
    {
        return DetalleCurso::with('materia')
            ->where('curso_id', $cursoId)
            ->whereHas('materia', function ($q) {
                $q->where('activa', true);
            })
            ->get()
            ->filter(function (DetalleCurso $dc) use ($alumnoId) {
                $materia = $dc->materia;

                if (! $materia->es_opcional) {
                    return true;
                }

                // Materia opcional: verificar alumno_materia
                return DB::table('alumno_materia')
                    ->where('alumno_id', $alumnoId)
                    ->where('curso_id', $dc->curso_id)
                    ->where('materia_id', $dc->materia_id)
                    ->exists();
            })
            ->values();
    }

    /**
     * Validar notas para cada DetalleCurso esperado.
     *
     * Retorna coleccion de notas indexada por detalle_curso_id.
     */
    private function validarNotas(int $alumnoId, $detallesEsperados): array
    {
        $detalleIds = $detallesEsperados->pluck('id')->toArray();

        // Obtener todas las notas del alumno para estos detalles en una sola query
        $notas = Nota::where('alumno_id', $alumnoId)
            ->whereIn('detalle_curso_id', $detalleIds)
            ->get()
            ->keyBy('detalle_curso_id');

        // Verificar duplicados (mismo alumno + mismo detalle_curso)
        $conteoPorDetalle = Nota::where('alumno_id', $alumnoId)
            ->whereIn('detalle_curso_id', $detalleIds)
            ->selectRaw('detalle_curso_id, count(*) as total')
            ->groupBy('detalle_curso_id')
            ->havingRaw('count(*) > 1')
            ->pluck('detalle_curso_id')
            ->toArray();

        if (! empty($conteoPorDetalle)) {
            throw BoletaEmissionException::calificacionesPendientes([
                'Inconsistencia: múltiples notas para el mismo detalle_curso'
            ]);
        }

        $materiasFaltantes = [];
        $notasValidadas = [];

        foreach ($detallesEsperados as $dc) {
            $nota = $notas->get($dc->id);

            if (! $nota) {
                $materiasFaltantes[] = $dc->materia->nombre;
                continue;
            }

            // Verificar los 3 componentes numéricos + promedio
            if (
                $nota->laboratorio === null ||
                $nota->examen_teorico === null ||
                $nota->practica === null ||
                $nota->promedio === null
            ) {
                $materiasFaltantes[] = $dc->materia->nombre;
                continue;
            }

            $notasValidadas[$dc->id] = [
                'detalle_curso' => $dc,
                'nota'          => $nota,
            ];
        }

        if (! empty($materiasFaltantes)) {
            throw BoletaEmissionException::calificacionesPendientes($materiasFaltantes);
        }

        return $notasValidadas;
    }

    /**
     * Obtener el Director vigente para la fecha de emisión.
     */
    private function obtenerDirectorVigente(Carbon $fechaEmision): Director
    {
        $fecha = $fechaEmision->toDateString();

        $directores = Director::whereDate('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->get();

        if ($directores->isEmpty()) {
            throw BoletaEmissionException::sinDirectorVigente();
        }

        if ($directores->count() > 1) {
            throw BoletaEmissionException::multiplesDirectores();
        }

        return $directores->first();
    }

    /**
     * Validar que el Director tiene firma válida.
     */
    private function validarFirmaDirector(Director $director): void
    {
        if (empty($director->firma_path)) {
            throw BoletaEmissionException::directorSinFirma();
        }

        if (! Storage::disk('local')->exists($director->firma_path)) {
            throw BoletaEmissionException::directorSinFirma();
        }
    }

    /**
     * Calcular promedio general: promedio de los promedios de materias esperadas.
     */
    private function calcularPromedioGeneral(array $notasValidadas): int
    {
        $suma = 0;
        $count = count($notasValidadas);

        foreach ($notasValidadas as $data) {
            $suma += $data['nota']->promedio;
        }

        return (int) round($suma / $count);
    }

    /**
     * Calcular concepto general según promedio.
     */
    private function calcularConceptoGeneral(int $promedio): string
    {
        if ($promedio >= 9) return 'E';
        if ($promedio >= 8) return 'MB';
        if ($promedio >= 7) return 'B';
        if ($promedio >= 6) return 'R';
        return 'D';
    }

    /**
     * Calcular concepto por materia según promedio.
     */
    private function calcularConceptoMateria(int $promedio): string
    {
        if ($promedio >= 9) return 'E';
        if ($promedio >= 8) return 'MB';
        if ($promedio >= 7) return 'B';
        if ($promedio >= 6) return 'R';
        return 'D';
    }

    /**
     * Generar ruta temporal para la firma (basada en timestamp + random).
     */
    private function generarRutaTemporalFirma(string $rutaOriginal): string
    {
        $extension = pathinfo($rutaOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = 'temp_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extension;
        return 'boletas/firmas/temp/' . $nombreArchivo;
    }

    /**
     * Generar ruta histórica definitiva para la firma.
     */
    private function generarRutaFinalFirma(int $boletaId, string $rutaOriginal): string
    {
        $extension = pathinfo($rutaOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = 'firma.' . $extension;
        return "boletas/firmas/{$boletaId}/{$nombreArchivo}";
    }

    /**
     * Crear los DetalleBoleta para la boleta emitida.
     */
    private function crearDetallesBoleta(int $boletaId, array $notasValidadas): void
    {
        foreach ($notasValidadas as $data) {
            $dc = $data['detalle_curso'];
            $nota = $data['nota'];

            DetalleBoleta::create([
                'boleta_id'           => $boletaId,
                'detalle_curso_id'    => $dc->id,
                'materia_id'          => $dc->materia_id,
                'materia_nombre'      => $dc->materia->nombre,
                'materia_codigo'      => $dc->materia->codigo,
                'laboratorio'         => $nota->laboratorio,
                'examen_teorico'      => $nota->examen_teorico,
                'practica'            => $nota->practica,
                'conducta'            => $nota->conducta,
                'promedio'            => $nota->promedio,
            ]);
        }
    }

    /**
     * Determina si una QueryException corresponde a la violación
     * del índice único uk_boleta_alumno_curso (alumno_id + curso_id).
     *
     * MySQL: error 1062 + mensaje que contenga 'uk_boleta_alumno_curso'
     * SQLite: mensaje que contenga 'UNIQUE constraint failed: boletas.alumno_id, boletas.curso_id'
     */
    private function esDuplicadoBoleta(QueryException $e): bool
    {
        $errorInfo = $e->errorInfo;
        $message = $e->getMessage();

        // MySQL: driver error code 1062 (ER_DUP_ENTRY) y nombre de índice específico
        if (isset($errorInfo[1]) && $errorInfo[1] === 1062) {
            return str_contains($message, 'uk_boleta_alumno_curso');
        }

        // SQLite: mensaje típico "UNIQUE constraint failed: boletas.alumno_id, boletas.curso_id"
        return str_contains($message, 'UNIQUE constraint failed')
            && str_contains($message, 'boletas.alumno_id')
            && str_contains($message, 'boletas.curso_id');
    }
}