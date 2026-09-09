<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePagosUniqueMensualidad();
        $this->ensureInscripcionesUniqueAlumnoCurso();
    }

    public function down(): void
    {
        // Forward-only: no se eliminan restricciones que podrían ser preexistentes.
    }

    private function ensurePagosUniqueMensualidad(): void
    {
        $table = 'pagos';
        $columns = ['mensualidad_id'];
        $indexName = 'pagos_mensualidad_id_unique';

        $this->ensureRequiredStructure($table, $columns);

        if ($this->hasValidUniqueIndex($table, $columns, $indexName)) {
            return;
        }

        $duplicados = DB::table($table)
            ->whereNotNull('mensualidad_id')
            ->select('mensualidad_id')
            ->groupBy('mensualidad_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicados) {
            throw new RuntimeException(
                'Existen pagos duplicados para una misma mensualidad. '.
                'No se eliminaron ni modificaron filas.'
            );
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->unique('mensualidad_id', $indexName);
        });
    }

    private function ensureInscripcionesUniqueAlumnoCurso(): void
    {
        $table = 'inscripciones';
        $columns = ['alumno_id', 'curso_id'];
        $indexName = 'inscripciones_alumno_id_curso_id_unique';

        $this->ensureRequiredStructure($table, $columns);

        if ($this->hasValidUniqueIndex($table, $columns, $indexName)) {
            return;
        }

        $duplicados = DB::table($table)
            ->select('alumno_id', 'curso_id')
            ->groupBy('alumno_id', 'curso_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicados) {
            throw new RuntimeException(
                'Existen inscripciones duplicadas para el mismo alumno y curso. '.
                'No se eliminaron ni modificaron filas.'
            );
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->unique(['alumno_id', 'curso_id'], $indexName);
        });
    }

    private function ensureRequiredStructure(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "No existe la tabla requerida: {$table}."
            );
        }

        if (! Schema::hasColumns($table, $columns)) {
            throw new RuntimeException(
                "La tabla {$table} no contiene todas las columnas requeridas: ".
                implode(', ', $columns).'.'
            );
        }
    }

    private function hasValidUniqueIndex(
        string $table,
        array $expectedColumns,
        string $expectedName
    ): bool {
        $normalizedExpectedColumns = $this->normalizeColumns($expectedColumns);
        $normalizedExpectedName = strtolower($expectedName);
        $equivalentUniqueExists = false;

        foreach (Schema::getIndexes($table) as $index) {
            $name = strtolower((string) ($index['name'] ?? ''));
            $columns = $this->normalizeColumns($index['columns'] ?? []);
            $unique = (bool) ($index['unique'] ?? false);

            if ($name === $normalizedExpectedName) {
                if (! $unique || $columns !== $normalizedExpectedColumns) {
                    throw new RuntimeException(
                        "El índice {$expectedName} existe, pero no corresponde ".
                        'a la restricción unique esperada sobre ('.
                        implode(', ', $expectedColumns).').'
                    );
                }

                return true;
            }

            if ($unique && $columns === $normalizedExpectedColumns) {
                $equivalentUniqueExists = true;
            }
        }

        return $equivalentUniqueExists;
    }

    private function normalizeColumns(array $columns): array
    {
        return array_map(
            static fn (mixed $column): string => strtolower((string) $column),
            array_values($columns)
        );
    }
};
