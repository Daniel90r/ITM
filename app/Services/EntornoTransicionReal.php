<?php

namespace App\Services;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;

/** Precondiciones reales de SOLO LECTURA. No exporta ni genera permisos. */
class EntornoTransicionReal
{
    public function __construct(private DocumentosInscripcion $archivos, private ControlTransicionDocumental $control) {}

    public function autoridad(): string
    {
        return app(RutasAutorizacionDocumental::class)->raiz('autoridad');
    }

    public function verificarConexion(): void
    {
        if (!app()->runningInConsole() || app()->environment('testing') || !app()->environment('local')) {
            throw new RuntimeException('MODO_REAL_NO_PERMITIDO');
        }
        $c = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
        if ($c['driver'] !== 'mysql' || $c['host'] !== '127.0.0.1' || (int) $c['port'] !== 3306
            || $c['database'] !== 'itm' || !empty($c['read']) || !empty($c['write']) || !empty($c['unix_socket'])) {
            throw new RuntimeException('CONEXION_REAL_NO_PERMITIDA');
        }
        $conexion = DB::connection();
        if ($conexion->getConfig('read') || $conexion->getConfig('write') || $conexion->getConfig('unix_socket')) {
            throw new RuntimeException('CONEXION_EFECTIVA_NO_COINCIDE');
        }
        foreach (['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'itm'] as $k => $v) {
            if ($conexion->getConfig($k) !== $v) throw new RuntimeException('CONEXION_EFECTIVA_NO_COINCIDE');
        }
        if ((int) $conexion->getConfig('port') !== 3306 || $conexion->getDriverName() !== 'mysql'
            || $conexion->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('CONEXION_EFECTIVA_NO_COINCIDE');
        }
        $id = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, VERSION() AS version');
        if ($id->db !== 'itm' || (int) $id->port !== 3306 || $id->version !== '8.4.7') {
            throw new RuntimeException('SERVIDOR_REAL_NO_COINCIDE');
        }
    }

    public function preparar(string $cuarentena, string $claveSha): array
    {
        // Ningún test puede alcanzar el adaptador MySQL real, aunque firme un permiso ficticio.
        if (app()->environment('testing')) throw new RuntimeException('MODO_REAL_NO_PERMITIDO');
        if (!config('documentos_inscripcion.transition_guard_enabled')) throw new RuntimeException('BARRERA_REAL_DESACTIVADA');
        foreach (['enabled', 'history_enabled', 'legacy_read_enabled'] as $flag) {
            if (config('documentos_inscripcion.'.$flag)) throw new RuntimeException('OPCIONES_INCOMPATIBLES');
        }
        app(ConsumosAutorizacionDocumental::class)->exigirCanalReal();
        $rutasSeguras = app(RutasAutorizacionDocumental::class)->verificar();
        $registro = app(RutasAutorizacionDocumental::class)->raiz('consumos');
        $autoridad = $this->autoridad();
        $clave = $autoridad.'/clave-publica.txt';
        if (!is_file($clave) || !is_dir($registro) || !preg_match('/\A[a-f0-9]{64}\z/D', $claveSha)
            || !hash_equals($claveSha, hash_file('sha256', $clave))) throw new RuntimeException('AUTORIDAD_NO_ESTABLECIDA');
        $web = dirname(base_path()); // Conservador: Wamp publica www; no aceptar excepciones implícitas.
        $publico = rtrim((string) config('filesystems.disks.public.root'), '/\\');
        $privado = rtrim((string) config('filesystems.disks.inscripciones.root'), '/\\');
        $esperada = app(RutasAutorizacionDocumental::class)->raiz('cuarentena');
        if ($this->normal($cuarentena) !== $this->normal($esperada)
            || config('filesystems.disks.inscripciones.driver') !== 'local'
            || config('filesystems.disks.public.driver') !== 'local'
            || config('filesystems.disks.inscripciones.visibility') !== 'private'
            || config('filesystems.disks.inscripciones.serve', false)) throw new RuntimeException('DESTINOS_REALES_NO_PERMITIDOS');
        $raices = [$autoridad, $privado, $cuarentena, $registro, app(RutasAutorizacionDocumental::class)->raiz('permisos'), $this->control->directorio()];
        foreach ($raices as $n => $raiz) {
            $this->verificarFueraDeWeb($raiz, $web, $publico);
            foreach ($raices as $otro => $r) {
                if ($n !== $otro && $this->control->dentro($raiz, $r)) throw new RuntimeException('RAICES_PRIVADAS_SUPERPUESTAS');
            }
        }
        foreach (array_merge($raices, [$clave]) as $ruta) $this->verificarAcl($ruta);
        foreach (['public', 'inscripciones'] as $disco) {
            $raiz = config("filesystems.disks.$disco.root");
            if (!is_dir($raiz)) throw new RuntimeException('RAIZ_REAL_NO_PREPARADA');
            if ($this->normal(Storage::disk($disco)->path('')) !== $this->normal($raiz)) {
                throw new RuntimeException('DISCO_EFECTIVO_NO_COINCIDE');
            }
        }
        $this->verificarConexion();
        $this->verificarEscritores();
        $tablas = DB::select("SELECT TABLE_NAME AS nombre, ENGINE AS motor FROM information_schema.tables WHERE table_schema='itm' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
        if (count($tablas) !== 29) throw new RuntimeException('INVENTARIO_SQL_NO_COINCIDE');
        $hashes = [];
        foreach ($tablas as $tabla) {
            if ($tabla->motor !== 'InnoDB' || !preg_match('/\A[a-zA-Z0-9_]+\z/D', $tabla->nombre)) throw new RuntimeException('MOTOR_NO_TRANSACCIONAL');
            if ($tabla->nombre === 'documentos_inscripcion') continue;
            $filas = [];
            foreach (DB::connection()->getPdo()->query('SELECT * FROM `'.$tabla->nombre.'`', \PDO::FETCH_ASSOC) as $fila) {
                $filas[] = hash('sha256', serialize($fila));
            }
            sort($filas, SORT_STRING);
            $hashes[$tabla->nombre] = ['filas' => count($filas), 'sha256' => hash('sha256', implode('', $filas))];
        }
        return ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'itm', 'version' => '8.4.7',
            'server_uuid' => DB::selectOne('SELECT @@server_uuid AS uuid')->uuid,
            'proyecto_sha256' => hash('sha256', $this->normal(base_path())),
            'autoridad' => $this->normal($autoridad), 'clave_publica_sha256' => $claveSha,
            'ejecutor_sid' => config('documentos_inscripcion.executor_sid'),
            'registro_consumos_raiz' => $this->normal($registro),
            'registro_consumos_sha256' => hash('sha256', json_encode(config('documentos_inscripcion.consumption_registry'), JSON_THROW_ON_ERROR)),
            'rutas_autorizacion_sha256' => hash('sha256', json_encode($rutasSeguras, JSON_THROW_ON_ERROR)),
            'publico' => $this->normal($publico), 'privado' => $this->normal($privado),
            'cuarentena' => $this->normal($cuarentena), 'barrera' => $this->normal($this->control->directorio()),
            'datos_originales_sha256' => hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR))];
    }

    public function verificarFueraDeWeb(string $ruta, string $web, string $publico): void
    {
        $this->archivos->verificarRutaAbsoluta($ruta);
        if ($this->control->dentro($ruta, $web) || $this->control->dentro($ruta, base_path())
            || $this->control->dentro($ruta, $publico) || $this->control->dentro($publico, $ruta)) {
            throw new RuntimeException('DESTINO_PRIVADO_PUBLICABLE');
        }
    }

    public function verificarEscritores(): void
    {
        $grants = DB::select('SHOW GRANTS FOR CURRENT_USER()'); $visible = false;
        foreach ($grants as $g) foreach ((array) $g as $linea) {
            if (preg_match('/GRANT (?:ALL PRIVILEGES|.*\bPROCESS\b.*) ON \*\.\* /', $linea)) $visible = true;
        }
        if (!$visible) throw new RuntimeException('ESCRITORES_NO_VERIFICABLES');
        $otros = DB::select("SELECT ID FROM information_schema.processlist WHERE ID<>CONNECTION_ID() AND COMMAND<>'Daemon'");
        if ($otros || (int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.innodb_trx')->n
            || (int) DB::selectOne('SELECT COUNT(*) AS n FROM jobs')->n
            || (int) DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.events WHERE event_schema='itm' AND status='ENABLED'")->n) {
            throw new RuntimeException('ESCRITORES_O_AUTOMATISMOS_PRESENTES');
        }
    }

    /** Solo consulta DACL; una prueba efectiva de escritura requerirá autorización posterior. */
    public function verificarAcl(string $ruta): void
    {
        $this->archivos->verificarRutaAbsoluta($ruta);
        if (!file_exists($ruta)) throw new RuntimeException('ACL_REAL_PENDIENTE');
        $encoded = base64_encode(json_encode($ruta, JSON_THROW_ON_ERROR));
        $script = '$ErrorActionPreference="Stop"; $p=ConvertFrom-Json ([Text.Encoding]::UTF8.GetString([Convert]::FromBase64String("'.$encoded.'")));'
            .'$acl=Get-Acl -LiteralPath $p; $me=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value;'
            .'$allow=@($me,"S-1-5-18","S-1-5-32-544"); $ok=$true;'
            .'foreach($r in $acl.Access){$sid=$r.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value;'
            .'if($r.AccessControlType -eq "Allow" -and $sid -notin $allow){$ok=$false}};'
            .'$owner=$acl.GetOwner([Security.Principal.SecurityIdentifier]).Value; if($owner -notin $allow){$ok=$false};'
            .'if($ok){Write-Output "PRIVATE_ACL_OK"}else{exit 2}';
        $p = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', '-'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($p)) throw new RuntimeException('ACL_REAL_NO_VERIFICABLE');
        fwrite($pipes[0], $script."\n"); fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p) !== 0 || trim($out) !== 'PRIVATE_ACL_OK') throw new RuntimeException('ACL_REAL_NO_PRIVADA');
    }

    private function normal(string $p): string { return strtolower(rtrim(str_replace('\\', '/', $p), '/')); }
}
