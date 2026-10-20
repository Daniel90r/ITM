<?php

// Aislar también pruebas heredadas que no llaman a Storage::fake().
// Ninguna escritura de la suite debe alcanzar las carpetas documentales reales.
$testStorage = sys_get_temp_dir().'/itm-documentos-tests-'.bin2hex(random_bytes(8));
foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
    if (!mkdir($testStorage.'/'.$dir, 0700, true) && !is_dir($testStorage.'/'.$dir)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento temporal de pruebas.');
    }
}
putenv('LARAVEL_STORAGE_PATH='.$testStorage);
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $testStorage;
// Las rutas seleccionadas en .env nunca deben alcanzar la suite. El control
// vacío conserva el fallback relativo al storage temporal de cada aplicación.
foreach ([
    'INSCRIPCION_DOCUMENTOS_ROOT' => $testStorage.'/app/private/inscripciones',
    'INSCRIPCION_TRANSICION_CONTROL_ROOT' => '',
    'INSCRIPCION_DOCUMENTOS_PRIVADOS' => 'false',
    'INSCRIPCION_DOCUMENTOS_LEGACY_READ' => 'false',
    'INSCRIPCION_DOCUMENTOS_HISTORIAL' => 'false',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/sqlite-memory-bootstrap.php';
