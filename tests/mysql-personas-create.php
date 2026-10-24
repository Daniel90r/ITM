<?php

// Administrative provisioning only. No existing schema is selected or changed.
require dirname(__DIR__).'/vendor/autoload.php';
$env = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__).'/.env'));
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int) ($env['DB_PORT'] ?? 3306);
if (!in_array($host,['127.0.0.1','localhost','::1'],true) || !empty($env['DB_URL'])) throw new RuntimeException('Local explicit WAMP connection required');
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
$preexisting = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($pdo->query("SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE='InnoDB'")->fetchColumn(), ['YES','DEFAULT'],true)) throw new RuntimeException('InnoDB required');
$nonce = bin2hex(random_bytes(5));
$base = 'itm_estado_test_'.date('Ymd').'_'.$nonce;
$databases = [$base,$base.'_ddl'];
foreach ($databases as $db) if (in_array($db,$preexisting,true) || !preg_match('/^itm_estado_test_[a-z0-9_]+$/',$db)) throw new RuntimeException('Database already exists or invalid name');
$manifest = ['host'=>$host,'port'=>$port,'username'=>'itm_test_'.$nonce,'password'=>bin2hex(random_bytes(24)),
    'databases'=>$databases,'preexisting'=>$preexisting,'owned'=>[],'nonce'=>$nonce,'created'=>date(DATE_ATOM),
    'server'=>$pdo->query('SELECT @@server_uuid uuid, @@port port, CURRENT_USER() identity, VERSION() version')->fetch(PDO::FETCH_ASSOC)];
$path = sys_get_temp_dir().'/itm-mysql-'.$nonce.'.json';
if (file_exists($path)) throw new RuntimeException('Manifest collision');
file_put_contents($path,json_encode($manifest,JSON_THROW_ON_ERROR));
foreach ($databases as $db) {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $manifest['owned'][]=$db;
    file_put_contents($path,json_encode($manifest,JSON_THROW_ON_ERROR));
}
$account = $pdo->quote($manifest['username'])."@'localhost'";
$pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($manifest['password']));
$literalSchemas = (bool) $pdo->query('SELECT @@partial_revokes')->fetchColumn();
foreach ($databases as $db) {
    $grantSchema = $literalSchemas ? $db : str_replace('_','\\_',$db);
    $pdo->exec("GRANT ALL PRIVILEGES ON `$grantSchema`.* TO $account");
}
echo json_encode(['manifest'=>$path,'databases'=>$databases,'identity'=>$manifest['username'].'@localhost','credentials'=>'external manifest only']).PHP_EOL;
