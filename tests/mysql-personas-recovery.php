<?php

require __DIR__.'/mysql-personas-bootstrap.php';
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$m = json_decode(file_get_contents(getenv('ITM_MYSQL_MANIFEST')), true, flags: JSON_THROW_ON_ERROR);
$actual = DB::selectOne('SELECT DATABASE() db, @@server_uuid uuid, CURRENT_USER() identity');
if ($actual->db !== $m['owned'][1] || in_array($actual->db, $m['preexisting'], true) || $actual->uuid !== $m['server']['uuid'] || $actual->identity !== $m['username'].'@localhost') {
    throw new RuntimeException('Independent owned recovery database required');
}
$checks = 0;
$phases = [];
function verify(bool $ok, string $message): void
{
    global $checks;
    $checks++;
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function rejected(callable $action, string $name): void
{
    try {
        $action();
    } catch (QueryException $e) {
        verify(true, $name);

        return;
    }throw new RuntimeException('Invalid write accepted: '.$name);
}

try {
    if (Schema::hasTable('migrations')) {
        throw new RuntimeException('Recovery must start on the newly created empty database; do not reuse');
    }
    $interruption = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$interruption) {
        if ($interruption && str_contains(strtolower($sql), 'alter table `cambios_estado_personas` add index')) {
            $interruption = false;
            throw new RuntimeException('Synthetic DDL interruption after table creation');
        }
    });
    try {
        $kernel->call('migrate', ['--force' => true]);
        throw new RuntimeException('DDL interruption not reached');
    } catch (RuntimeException $e) {
        verify($e->getMessage() === 'Synthetic DDL interruption after table creation', 'Expected partial DDL failure');
    }
    verify(Schema::hasColumn('users', 'auth_version') && Schema::hasTable('cambios_estado_personas'), 'Partial objects preserved');
    $u = User::factory()->create(['role' => 'admin']);
    $u->forceFill(['auth_version' => 7])->save();
    $a = Alumno::factory()->create();
    $teacher = Maestro::factory()->create();
    $event = ['alumno_id' => $a->id, 'maestro_id' => null, 'administrador_id' => $u->id, 'estado_anterior' => 1, 'estado_nuevo' => 0, 'motivo' => 'Synthetic retained audit', 'registrado_at' => now()];
    DB::table('cambios_estado_personas')->insert($event);
    $snapshot = DB::table('cambios_estado_personas')->get()->toJson();
    verify($kernel->call('migrate', ['--force' => true]) === 0, 'Migration retry completes');
    $migration = require database_path('migrations/2026_10_24_000001_add_auth_version_and_persona_audit.php');
    $migration->up();
    $migration->up();
    verify($u->fresh()->auth_version === 7, 'Version not reset');
    verify(DB::table('cambios_estado_personas')->get()->toJson() === $snapshot, 'Audit preserved');
    $triggers = DB::select('SHOW TRIGGERS');
    verify(count(array_filter($triggers, fn ($t) => str_starts_with($t->Trigger, 'cep_validate_'))) === 2, 'Two triggers without duplicates');
    $indexes = Schema::getIndexes('cambios_estado_personas');
    verify(count(array_filter($indexes, fn ($i) => $i['columns'] === ['alumno_id', 'id'])) === 1, 'One restored alumno index');
    foreach (['none', 'both', 'same', 'range', 'fk'] as $kind) {
        $bad = $event;
        if ($kind === 'none') {
            $bad['alumno_id'] = null;
        }if ($kind === 'both') {
            $bad['maestro_id'] = $teacher->id;
        }if ($kind === 'same') {
            $bad['estado_nuevo'] = 1;
        }if ($kind === 'range') {
            $bad['estado_nuevo'] = 2;
        }if ($kind === 'fk') {
            $bad['alumno_id'] = 999999;
        }rejected(fn () => DB::table('cambios_estado_personas')->insert($bad), $kind);
    }
    rejected(fn () => DB::table('cambios_estado_personas')->update(['estado_nuevo' => 1]), 'Update integrity');
    $d = DetalleCurso::factory()->create(['maestro_id' => $teacher->id]);
    $assignment = AsignacionDocente::create(['maestro_id' => $teacher->id, 'detalle_curso_id' => $d->id, 'estado' => 'finalizada']);
    $closure = ['asignacion_id' => $assignment->id, 'administrador_id' => $u->id, 'motivo' => 'Synthetic scheduled closure', 'registrado_at' => now()];
    DB::table('cierres_asignaciones_programadas')->insert($closure);
    rejected(fn () => DB::table('cierres_asignaciones_programadas')->insert($closure), 'Duplicate closure');
    $before = DB::table('cambios_estado_personas')->count();
    try {
        DB::transaction(function () use ($event) {
            DB::table('cambios_estado_personas')->insert($event);
            throw new RuntimeException('Synthetic rollback');
        });
    } catch (RuntimeException $e) {
        verify($e->getMessage() === 'Synthetic rollback', 'Injected transaction failure');
    }verify(DB::table('cambios_estado_personas')->count() === $before, 'Audit rollback');
    // Owned disposable column only: demonstrate safe refusal, then restore its exact definition.
    DB::statement('ALTER TABLE users MODIFY auth_version INT UNSIGNED NOT NULL DEFAULT 2');
    try {
        $migration->up();
        throw new RuntimeException('Incompatible default accepted');
    } catch (RuntimeException $e) {
        verify(str_contains($e->getMessage(), 'auth_version incompatible'), 'Incompatible schema rejected');
    }
    verify($u->fresh()->auth_version === 7, 'Invalid schema did not reset data');
    DB::statement('ALTER TABLE users MODIFY auth_version INT UNSIGNED NOT NULL DEFAULT 1');
    $migration->up();
    verify(DB::table('cambios_estado_personas')->get()->toJson() === $snapshot, 'Recovery preserves audit');
    $tables = DB::select('SELECT TABLE_NAME name, ENGINE engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\'');
    verify(count(array_filter($tables, fn ($t) => $t->engine !== 'InnoDB')) === 0, 'All tables InnoDB');
    $out = ['database' => $actual->db, 'result' => 'passed', 'assertions' => $checks, 'triggers' => array_map(fn ($t) => $t->Trigger, $triggers), 'audit_foreign_keys' => Schema::getForeignKeys('cambios_estado_personas'), 'columns' => Schema::getColumns('cambios_estado_personas'), 'tables' => count($tables), 'engine' => 'InnoDB'];
    file_put_contents(storage_path('mysql-recovery-results.json'), json_encode($out, JSON_PRETTY_PRINT));
    echo json_encode($out).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'failed', 'assertions' => $checks, 'exception' => get_class($e), 'message' => $e->getMessage()]).PHP_EOL;
    exit(1);
}
