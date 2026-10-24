<?php

// Isolation is established before Laravel or RefreshDatabase may run.
$root = str_replace('\\', '/', sys_get_temp_dir()).'/itm-personas-'.bin2hex(random_bytes(8));
mkdir($root, 0700, true);
$root = preg_replace('/^[A-Za-z]:/', '', $root);
foreach (['SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr', 'APP_CONFIG_CACHE' => $root.'/config.php', 'APP_ROUTES_CACHE' => $root.'/routes.php', 'APP_SERVICES_CACHE' => $root.'/services.php', 'APP_PACKAGES_CACHE' => $root.'/packages.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/documentos-isolated-bootstrap.php';
