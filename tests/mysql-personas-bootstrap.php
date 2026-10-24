<?php

use Illuminate\Contracts\Console\Kernel;

// Credentials live only in the external ownership manifest, never in the repository.
$manifestPath = getenv('ITM_MYSQL_MANIFEST');
if (! $manifestPath || ! is_file($manifestPath)) {
    throw new RuntimeException('Ownership manifest required');
}
$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$database = getenv('ITM_MYSQL_DATABASE') ?: $manifest['databases'][0];
if (! in_array($database, $manifest['owned'], true) || in_array($database, $manifest['preexisting'], true) || $database === 'itm' || ! str_starts_with($database, 'itm_estado_test_')) {
    throw new RuntimeException('Database not owned by this run');
}
$temporary = str_replace('\\', '/', sys_get_temp_dir()).'/itm-mysql-storage-'.$manifest['nonce'];
foreach (['app/public', 'app/private', 'framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'bootstrap'] as $dir) {
    if (! is_dir($temporary.'/'.$dir)) {
        mkdir($temporary.'/'.$dir, 0700, true);
    }
}
$cache = preg_replace('/^[A-Za-z]:/', '', $temporary).'/bootstrap';
foreach ([
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $manifest['host'], 'DB_PORT' => (string) $manifest['port'],
    'DB_DATABASE' => $database, 'DB_USERNAME' => $manifest['username'], 'DB_PASSWORD' => $manifest['password'], 'DB_URL' => '', 'DB_SOCKET' => '',
    'LARAVEL_STORAGE_PATH' => $temporary, 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr',
    'APP_CONFIG_CACHE' => $cache.'/config.php', 'APP_ROUTES_CACHE' => $cache.'/routes.php', 'APP_SERVICES_CACHE' => $cache.'/services.php', 'APP_PACKAGES_CACHE' => $cache.'/packages.php',
    'INSCRIPCION_DOCUMENTOS_ROOT' => $temporary.'/app/private/inscripciones', 'INSCRIPCION_TRANSICION_CONTROL_ROOT' => '',
    'INSCRIPCION_DOCUMENTOS_PRIVADOS' => 'false', 'INSCRIPCION_DOCUMENTOS_LEGACY_READ' => 'false', 'INSCRIPCION_DOCUMENTOS_HISTORIAL' => 'false',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = $app['db']->connection();
$actual = $connection->selectOne('SELECT DATABASE() db, @@server_uuid uuid, @@port port, CURRENT_USER() identity');
if ($app->configurationIsCached() || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database || $actual->db !== $database || $actual->uuid !== $manifest['server']['uuid'] || (int) $actual->port !== $manifest['port'] || $actual->identity !== $manifest['username'].'@localhost') {
    throw new RuntimeException('Effective connection failed ownership checks');
}
$connection->statement('SET SESSION innodb_lock_wait_timeout=8');
fwrite(STDERR, json_encode(['isolated_database' => $actual->db, 'server' => $manifest['host'], 'port' => $actual->port, 'driver' => $connection->getDriverName(), 'identity' => $actual->identity]).PHP_EOL);
$app['db']->disconnect();
restore_error_handler();
restore_exception_handler();
unset($app);
