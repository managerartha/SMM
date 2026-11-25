<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Lokasi kode aplikasi. Di Hostinger, kode berada di luar public_html (lihat docs/DEPLOY_HOSTINGER.md)
// dan baris ini diganti oleh skrip build menjadi __DIR__.'/../smm'.
$base = __DIR__.'/..';

// Instalasi pertama tanpa SSH: buat APP_KEY otomatis bila masih kosong di .env.
if (is_file($env = $base.'/.env') && is_writable($env)) {
    $content = (string) file_get_contents($env);
    if (preg_match('/^APP_KEY=\s*$/m', $content)) {
        $key = 'base64:'.base64_encode(random_bytes(32));
        file_put_contents($env, preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $content), LOCK_EX);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
