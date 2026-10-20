<?php

// Bootstrap explícito para ejecutar estas pruebas sin leer una configuración
// cacheada ni permitir que variables del host apunten a la base de datos real.
foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'APP_CONFIG_CACHE' => __DIR__.'/config-sqlite-memory-no-cache.php',
    'APP_ROUTES_CACHE' => __DIR__.'/routes-sqlite-memory-no-cache.php',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:'
    || config('database.connections.sqlite.url')) {
    throw new RuntimeException('Pruebas bloqueadas: se requiere SQLite en memoria sin DB_URL.');
}
// Comprobar el controlador efectivo antes de que PHPUnit ejecute migraciones.
if ($app['db']->connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    throw new RuntimeException('El controlador efectivo no es SQLite.');
}
$app['db']->disconnect();
// No dejar manejadores de este arranque de diagnóstico en el estado inicial
// de PHPUnit; cada aplicación de prueba instala sus propios manejadores.
restore_error_handler();
restore_exception_handler();
unset($app);
