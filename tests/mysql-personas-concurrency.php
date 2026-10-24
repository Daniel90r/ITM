<?php

require __DIR__.'/mysql-personas-bootstrap.php';

use App\Http\Middleware\EstadoInstitucional;
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CambioEstadoPersona;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Nota;
use App\Models\User;
use App\Services\CambiarEstadoPersona;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$manifest = json_decode(file_get_contents(getenv('ITM_MYSQL_MANIFEST')), true, flags: JSON_THROW_ON_ERROR);
$db = DB::selectOne('SELECT DATABASE() db, @@server_uuid uuid, CURRENT_USER() identity');
if (! in_array($db->db, $manifest['owned'], true) || in_array($db->db, $manifest['preexisting'], true) || $db->uuid !== $manifest['server']['uuid'] || $db->identity !== $manifest['username'].'@localhost') {
    throw new RuntimeException('Unsafe connection');
}
DB::statement('SET SESSION innodb_lock_wait_timeout=8');
$directory = storage_path('concurrency');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$service = app(CambiarEstadoPersona::class);

function httpAction(array $p): int
{
    Auth::setUser(User::findOrFail($p['admin']));
    $request = Request::create($p['url'], $p['method'] ?? 'PATCH', $p['data'] ?? []);
    $response = app(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    if ($response->getStatusCode() >= 500) {
        throw new RuntimeException('HTTP server error '.$response->getStatusCode());
    }
    if ($response->getStatusCode() >= 400) {
        throw new RuntimeException('HTTP rejected '.$response->getStatusCode());
    }
    $errors = session('errors');
    if ($errors instanceof ViewErrorBag && $errors->any()) {
        throw ValidationException::withMessages($errors->getBag('default')->getMessages());
    }
    if (is_array($errors) && $errors) {
        throw ValidationException::withMessages($errors);
    }

    return $response->getStatusCode();
}

if (($argv[1] ?? '') === 'worker') {
    $p = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    file_put_contents($argv[2].'.ready', json_encode(['connection' => DB::selectOne('SELECT CONNECTION_ID() id')->id]));
    $start = microtime(true);
    try {
        $admin = User::findOrFail($p['admin']);
        if ($p['action'] === 'state' || $p['action'] === 'fail') {
            if ($p['action'] === 'fail') {
                CambioEstadoPersona::creating(function ($event) {
                    $event->estado_nuevo = $event->estado_anterior;
                });
            }
            $result = $service->ejecutar($p['tipo'], $p['id'], $p['activo'] ?? false, $p['esperado'] ?? true, 'Synthetic concurrent state change', $admin, $p['revision'] ?? 0);
        } elseif ($p['action'] === 'scheduled') {
            $result = DB::transaction(function () use ($p) {
                $m = Maestro::whereKey($p['id'])->where('activo', true)->lockForUpdate()->firstOrFail();

                return AsignacionDocente::create(['maestro_id' => $m->id, 'detalle_curso_id' => $p['detalle'], 'estado' => 'programada', 'asignado_por' => $p['admin']])->id;
            });
        } elseif ($p['action'] === 'access') {
            $u = User::findOrFail($p['id']);
            Auth::setUser($u);
            $r = Request::create('/alumno/pagos');
            $r->setUserResolver(fn () => $u);
            $r->setLaravelSession(app('session')->driver());
            $r->session()->put('auth_version', $p['version']);
            $result = app(EstadoInstitucional::class)->handle($r, fn () => response('unexpected access', 200))->getStatusCode();
        } else {
            $result = httpAction($p);
        }
        $out = ['ok' => true, 'result' => $result];
    } catch (Throwable $e) {
        $out = ['ok' => false, 'exception' => get_class($e), 'code' => $e->getCode(), 'message' => $e->getMessage()];
    }
    $out['elapsed_ms'] = (int) ((microtime(true) - $start) * 1000);
    echo json_encode($out, JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit;
}

$assertions = 0;
$results = [];
function check(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function maestro(): Maestro
{
    return Maestro::factory()->create(['user_id' => User::factory()->create(['role' => 'docente'])->id]);
}
function detalle(?Maestro $m = null): DetalleCurso
{
    return DetalleCurso::factory()->create(['maestro_id' => $m?->id]);
}
function race(string $name, string $table, int $id, array $jobs, ?callable $whileLocked = null): array
{
    global $directory;
    $processes = [];
    DB::beginTransaction();
    DB::table($table)->where('id', $id)->lockForUpdate()->first();
    try {
        foreach ($jobs as $i => $job) {
            $file = $directory.'/'.$name.'-'.$i.'-'.bin2hex(random_bytes(3)).'.json';
            file_put_contents($file, json_encode($job));
            $p = new Process([PHP_BINARY, '-d', 'memory_limit=1024M', '-d', 'xdebug.mode=off', __FILE__, 'worker', $file], dirname(__DIR__), null, null, 25);
            $p->start();
            $processes[] = [$p, $file];
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = true;
            foreach ($processes as [$p,$file]) {
                $ready = $ready && is_file($file.'.ready');
                if (! $p->isRunning() && ! is_file($file.'.ready')) {
                    throw new RuntimeException('Worker bootstrap failed');
                }
            }if ($ready) {
                break;
            }usleep(50000);
        } while (microtime(true) < $deadline);
        if (! $ready) {
            throw new RuntimeException('Workers did not reach barrier');
        }
        usleep(500000);
        $blocked = [];
        foreach (DB::select('SHOW PROCESSLIST') as $connection) {
            $blocked[(int) $connection->Id] = $connection->State;
        }
        if ($whileLocked) {
            $whileLocked();
        }
        DB::commit();
        $out = [];
        foreach ($processes as [$p,$file]) {
            $p->wait();
            $lines = explode("\n", trim($p->getOutput()));
            $value = json_decode(end($lines), true);
            if (! $value) {
                throw new RuntimeException('Worker returned no result');
            }$value['connection'] = json_decode(file_get_contents($file.'.ready'), true)['connection'];
            $value['server_state_at_barrier'] = $blocked[$value['connection']] ?? null;
            $out[] = $value;
        }
        foreach ($out as $i => $value) {
            if (! $value['ok'] && ! in_array($value['exception'], [ValidationException::class, ModelNotFoundException::class], true) && $value['message'] !== 'HTTP rejected 404' && ! (($jobs[$i]['action'] ?? '') === 'fail' && $value['exception'] === QueryException::class && (string) $value['code'] === '45000')) {
                throw new RuntimeException('Unexpected worker failure: '.$value['exception'].': '.$value['message']);
            }
        }

        return $out;
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }foreach ($processes as [$p]) {
            if ($p->isRunning()) {
                $p->stop(1);
            }
        }
    }
}

try {
    $deadlocksBefore = DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_deadlocks'")?->Value;
    $admin = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $u = User::factory()->create(['role' => 'alumno']);
    $a = Alumno::factory()->create(['user_id' => $u->id]);
    $job = ['action' => 'state', 'admin' => $admin->id, 'tipo' => 'alumnos', 'id' => $a->id];
    $r = race('A', 'users', $u->id, [$job, array_replace($job, ['admin' => $otherAdmin->id])]);
    check(count(array_filter($r, fn ($x) => $x['ok'])) === 1, 'A: exactly one transition');
    check(! $a->fresh()->activo, 'A: inactive');
    check(CambioEstadoPersona::where('alumno_id', $a->id)->count() === 1, 'A: single audit');
    check($r[0]['connection'] !== $r[1]['connection'], 'A: independent connections');
    $results['A'] = $r;

    $u = User::factory()->create(['role' => 'alumno']);
    $a = Alumno::factory()->create(['user_id' => $u->id]);
    $job = ['action' => 'state', 'admin' => $admin->id, 'tipo' => 'alumnos', 'id' => $a->id];
    $r = race('B', 'users', $u->id, [$job], function () use ($service, $a, $admin) {
        $service->ejecutar('alumnos', $a->id, false, true, 'Cycle deactivation', $admin, 0);
        $service->ejecutar('alumnos', $a->id, true, false, 'Cycle reactivation', $admin, $service->revision($a));
    });
    check(! $r[0]['ok'], 'B: obsolete confirmation rejected');
    check($a->fresh()->activo, 'B: remains active');
    check(CambioEstadoPersona::where('alumno_id', $a->id)->count() === 2, 'B: no stale audit');
    $results['B'] = $r;

    foreach (['C', 'D'] as $case) {
        $m = maestro();
        $d = detalle();
        $state = ['action' => 'state', 'admin' => $admin->id, 'tipo' => 'maestros', 'id' => $m->id];
        $assign = $case === 'C' ? ['action' => 'http', 'admin' => $otherAdmin->id, 'url' => '/admin/maestros/'.$m->id.'/asignar', 'method' => 'POST', 'data' => ['curso_id' => $d->curso_id, 'materia_ids' => [$d->materia_id]]] : ['action' => 'scheduled', 'admin' => $otherAdmin->id, 'id' => $m->id, 'detalle' => $d->id];
        $r = race($case, 'maestros', $m->id, [$assign, $state]);
        $open = AsignacionDocente::where('maestro_id', $m->id)->whereIn('estado', ['activa', 'programada'])->count();
        check($m->fresh()->activo || $open === 0, $case.': no inactive teacher with open assignment');
        check(count(array_filter($r, fn ($x) => $x['ok'])) === 1, $case.': competing write rejected');
        check($r[0]['connection'] !== $r[1]['connection'], $case.': independent connections');
        $results[$case] = ['workers' => $r, 'active' => $m->fresh()->activo, 'open_assignments' => $open];
    }

    $m = maestro();
    $d = detalle($m);
    $assignment = AsignacionDocente::create(['maestro_id' => $m->id, 'detalle_curso_id' => $d->id, 'estado' => 'programada', 'fecha_inicio' => '2030-01-01']);
    $j = ['action' => 'http', 'admin' => $admin->id, 'url' => '/admin/asignaciones/'.$assignment->id.'/finalizar-programada', 'data' => ['motivo' => 'Concurrent explicit scheduled closure']];
    $r = race('E', 'maestros', $m->id, [$j, array_replace($j, ['admin' => $otherAdmin->id])]);
    check(count(array_filter($r, fn ($x) => $x['ok'])) === 1, 'E: single closure');
    check($assignment->fresh()->estado === 'finalizada', 'E: finalized');
    check(DB::table('cierres_asignaciones_programadas')->where('asignacion_id', $assignment->id)->count() === 1, 'E: single closure audit');
    check($assignment->fresh()->fecha_inicio->toDateString() === '2030-01-01' && $assignment->fresh()->fecha_fin === null, 'E: original dates');
    $results['E'] = $r;

    $old = maestro();
    $new = maestro();
    $d = detalle($old);
    $assignment = AsignacionDocente::create(['maestro_id' => $old->id, 'detalle_curso_id' => $d->id, 'estado' => 'activa']);
    $student = Alumno::factory()->create();
    $note = Nota::factory()->create(['alumno_id' => $student->id, 'detalle_curso_id' => $d->id, 'registrado_por' => $old->id]);
    $j = ['action' => 'http', 'admin' => $otherAdmin->id, 'url' => '/admin/maestros/'.$old->id.'/programas/'.$d->id.'/cambiar-maestro', 'method' => 'PUT', 'data' => ['nuevo_maestro_id' => $new->id, 'motivo_cambio' => 'Concurrent permanent teacher substitution']];
    $r = race('F', 'maestros', $new->id, [$j, ['action' => 'state', 'admin' => $admin->id, 'tipo' => 'maestros', 'id' => $new->id]]);
    $open = AsignacionDocente::where('maestro_id', $new->id)->where('estado', 'activa')->count();
    check($new->fresh()->activo || $open === 0, 'F: no assignment to suspended recipient');
    check(count(array_filter($r, fn ($x) => $x['ok'])) === 1, 'F: single winning action');
    check($note->fresh()->registrado_por === $old->id, 'F: original author');
    $results['F'] = ['workers' => $r, 'recipient_active' => $new->fresh()->activo, 'recipient_open' => $open];

    $old = maestro();
    $new = maestro();
    $d = detalle($old);
    $assignment = AsignacionDocente::create(['maestro_id' => $old->id, 'detalle_curso_id' => $d->id, 'estado' => 'activa']);
    $j = ['action' => 'http', 'admin' => $otherAdmin->id, 'url' => '/admin/maestros/'.$old->id.'/programas/'.$d->id.'/cambiar-maestro', 'method' => 'PUT', 'data' => ['nuevo_maestro_id' => $new->id, 'motivo_cambio' => 'Concurrent outgoing teacher substitution']];
    $r = race('F_outgoing', 'detalle_cursos', $d->id, [$j, ['action' => 'state', 'admin' => $admin->id, 'tipo' => 'maestros', 'id' => $old->id]]);
    $oldOpen = AsignacionDocente::where('maestro_id', $old->id)->where('estado', 'activa')->count();
    check($old->fresh()->activo || $oldOpen === 0, 'F outgoing: no inactive teacher with active assignment');
    check($r[0]['ok'], 'F outgoing: substitution completes');
    check($assignment->fresh()->estado === 'finalizada', 'F outgoing: history finalized');
    check($r[0]['connection'] !== $r[1]['connection'], 'F outgoing: independent connections');
    $results['F']['outgoing'] = ['workers' => $r, 'old_active' => $old->fresh()->activo, 'old_open' => $oldOpen];

    $u = User::factory()->create(['role' => 'alumno']);
    $a = Alumno::factory()->create(['user_id' => $u->id]);
    $r = race('G', 'users', $u->id, [['action' => 'access', 'admin' => $admin->id, 'id' => $u->id, 'version' => 1]], function () use ($service, $a, $admin) {
        $service->ejecutar('alumnos', $a->id, false, true, 'Deactivate concurrent session', $admin, 0);
        $service->ejecutar('alumnos', $a->id, true, false, 'Reactivate concurrent session', $admin, $service->revision($a));
    });
    check($r[0]['ok'] && $r[0]['result'] === 302, 'G: old session blocked after reactivation');
    check($a->fresh()->activo, 'G: active profile');
    $results['G'] = $r;

    $u = User::factory()->create(['role' => 'alumno']);
    $a = Alumno::factory()->create(['user_id' => $u->id]);
    $before = $u->fresh()->getAttributes();
    $r = race('H', 'users', $u->id, [['action' => 'fail', 'admin' => $admin->id, 'tipo' => 'alumnos', 'id' => $a->id]]);
    check(! $r[0]['ok'] && (string) $r[0]['code'] === '45000', 'H: database trigger rejected audit write');
    check($a->fresh()->activo, 'H: profile rollback');
    check($before === $u->fresh()->getAttributes(), 'H: version and token rollback');
    check(CambioEstadoPersona::where('alumno_id', $a->id)->count() === 0, 'H: no audit persisted');
    $results['H'] = $r;
    // Explicitly prove the server blocks independent connections and honors a finite timeout.
    $probe = new PDO('mysql:host='.$manifest['host'].';port='.$manifest['port'].';dbname='.$db->db, $manifest['username'], $manifest['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    check($probe->query('SELECT DATABASE()')->fetchColumn() === $db->db, 'Lock probe ownership');
    DB::beginTransaction();
    DB::table('users')->where('id', $u->id)->lockForUpdate()->first();
    $probe->exec('SET SESSION innodb_lock_wait_timeout=1');
    $probe->beginTransaction();
    $started = microtime(true);
    $timeout = null;
    try {
        $probe->query('SELECT id FROM users WHERE id='.(int) $u->id.' FOR UPDATE');
    } catch (PDOException $e) {
        $timeout = $e->errorInfo[1] ?? null;
    } finally {
        if ($probe->inTransaction()) {
            $probe->rollBack();
        }DB::rollBack();
    }
    check((int) $timeout === 1205, 'Real lock wait timeout on independent connection');
    $deadlocksAfter = DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_deadlocks'")?->Value;
    $out = ['database' => $db->db, 'cases' => $results, 'assertions' => $assertions, 'result' => 'passed', 'lock_probe' => ['mysql_error' => $timeout, 'elapsed_ms' => (int) ((microtime(true) - $started) * 1000)], 'server_deadlocks_during_run' => $deadlocksBefore !== null && $deadlocksAfter !== null ? (int)$deadlocksAfter-(int)$deadlocksBefore : null];
    file_put_contents($directory.'/results.json',json_encode($out,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode($out,JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'failed', 'message' => $e->getMessage(), 'completed' => $results, 'current_workers' => $r ?? null, 'assertions' => $assertions],JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(1);
}
