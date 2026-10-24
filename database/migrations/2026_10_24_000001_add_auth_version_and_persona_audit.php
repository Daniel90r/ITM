<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException('Motor no verificado para la auditoría de personas.');
        }
        if (! Schema::hasColumn('users', 'auth_version')) {
            Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('auth_version')->default(1));
        }
        $versionColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'auth_version');
        if (! $versionColumn || $versionColumn['nullable'] || ! in_array(strtolower($versionColumn['type_name']), ['integer', 'int', 'bigint'], true) || trim((string) $versionColumn['default'], "'\"() ") !== '1' || ($driver !== 'sqlite' && ! str_contains(strtolower($versionColumn['type']), 'unsigned'))) {
            throw new RuntimeException('users.auth_version incompatible. Revisar manualmente sin reiniciar versiones.');
        }
        if (! Schema::hasTable('cambios_estado_personas')) {
            Schema::create('cambios_estado_personas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('alumno_id')->nullable();
                $t->unsignedInteger('maestro_id')->nullable();
                $t->unsignedInteger('administrador_id');
                $t->boolean('estado_anterior');
                $t->boolean('estado_nuevo');
                $t->string('motivo', 500);
                $t->unsignedInteger('auth_version')->nullable();
                $t->timestamp('registrado_at');
                $t->foreign('alumno_id')->references('id')->on('alumnos')->restrictOnDelete();
                $t->foreign('maestro_id')->references('id')->on('maestros')->restrictOnDelete();
                $t->foreign('administrador_id')->references('id')->on('users')->restrictOnDelete();
                $t->index(['alumno_id', 'id']);
                $t->index(['maestro_id', 'id']);
            });
        }
        if (! Schema::hasTable('cierres_asignaciones_programadas')) {
            Schema::create('cierres_asignaciones_programadas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('asignacion_id')->unique();
                $t->unsignedInteger('administrador_id');
                $t->string('motivo', 500);
                $t->timestamp('registrado_at');
                $t->foreign('asignacion_id')->references('id')->on('asignaciones_docentes')->restrictOnDelete();
                $t->foreign('administrador_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
        // A retry resumes only compatible additive objects; never remove existing data.
        foreach ([
            'cambios_estado_personas' => ['id', 'alumno_id', 'maestro_id', 'administrador_id', 'estado_anterior', 'estado_nuevo', 'motivo', 'auth_version', 'registrado_at'],
            'cierres_asignaciones_programadas' => ['id', 'asignacion_id', 'administrador_id', 'motivo', 'registrado_at'],
        ] as $table => $columns) {
            if (! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException('Esquema parcial incompatible en '.$table.'. Revisión manual sin borrar datos requerida.');
            }
        }
        foreach ([
            'cambios_estado_personas' => ['alumno_id' => 'alumnos', 'maestro_id' => 'maestros', 'administrador_id' => 'users'],
            'cierres_asignaciones_programadas' => ['asignacion_id' => 'asignaciones_docentes', 'administrador_id' => 'users'],
        ] as $table => $relations) {
            $keys = Schema::getForeignKeys($table);
            foreach ($relations as $column => $parent) {
                $found = collect($keys)->contains(fn ($key) => $key['columns'] === [$column] && $key['foreign_table'] === $parent && $key['foreign_columns'] === ['id'] && strtolower($key['on_delete']) === 'restrict');
                if (! $found) {
                    throw new RuntimeException('Clave foránea ausente o incompatible: '.$table.'.'.$column.'. Completar manualmente sin borrar datos.');
                }
            }
        }
        foreach ([
            ['cambios_estado_personas', ['alumno_id', 'id'], false, 'cambios_estado_personas_alumno_id_id_index'],
            ['cambios_estado_personas', ['maestro_id', 'id'], false, 'cambios_estado_personas_maestro_id_id_index'],
            ['cierres_asignaciones_programadas', ['asignacion_id'], true, 'cierres_asignaciones_programadas_asignacion_id_unique'],
        ] as [$table,$columns,$unique,$name]) {
            $exists = collect(Schema::getIndexes($table))->contains(fn ($index) => $index['columns'] === $columns && (! $unique || $index['unique']));
            if (! $exists) {
                if ($unique && DB::table($table)->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1')->exists()) {
                    throw new RuntimeException('Duplicados preexistentes en '.$table.'. No se eliminaron registros.');
                }
                Schema::table($table, function (Blueprint $t) use ($columns, $unique, $name) {
                    $unique ? $t->unique($columns, $name) : $t->index($columns, $name);
                });
            }
        }
        $invalid = '(alumno_id IS NULL AND maestro_id IS NULL) OR (alumno_id IS NOT NULL AND maestro_id IS NOT NULL) OR estado_anterior = estado_nuevo OR estado_anterior NOT IN (0,1) OR estado_nuevo NOT IN (0,1)';
        if (DB::table('cambios_estado_personas')->whereRaw($invalid)->exists()) {
            throw new RuntimeException('Auditoría preexistente inválida. No se modificó ni eliminó ningún evento.');
        }
        foreach (['insert', 'update'] as $operation) {
            $name = 'cep_validate_'.$operation;
            $condition = '(NEW.alumno_id IS NULL AND NEW.maestro_id IS NULL) OR (NEW.alumno_id IS NOT NULL AND NEW.maestro_id IS NOT NULL) OR NEW.estado_anterior = NEW.estado_nuevo OR NEW.estado_anterior NOT IN (0,1) OR NEW.estado_nuevo NOT IN (0,1)';
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS $name BEFORE $operation ON cambios_estado_personas WHEN $condition BEGIN SELECT RAISE(ABORT, 'Invalid person state audit'); END");
            } elseif (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->where('TRIGGER_NAME', $name)->exists()) {
                DB::unprepared("CREATE TRIGGER $name BEFORE $operation ON cambios_estado_personas FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid person state audit'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        // Audit records must never be deleted by an automatic rollback.
        throw new RuntimeException('Reversión manual requerida: conservar auditoría y revocación de sesiones.');
    }
};
