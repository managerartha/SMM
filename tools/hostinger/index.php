<?php

/*
 * Pintu masuk SMM untuk susunan "satu folder" di Hostinger (seluruh isi ZIP berada di public_html).
 * Semua permintaan dialihkan .htaccess ke berkas ini, sehingga .env, storage, vendor, dll.
 * tidak pernah bisa dibuka langsung dari browser.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = __DIR__;

// Pemasangan pertama: buat .env dari contoh dan APP_KEY otomatis.
if (! is_file($base.'/.env') && is_file($base.'/.env.contoh')) {
    @copy($base.'/.env.contoh', $base.'/.env');
    @chmod($base.'/.env', 0600);
}
if (is_file($env = $base.'/.env') && is_writable($env)) {
    $content = (string) file_get_contents($env);
    if (preg_match('/^APP_KEY=\s*$/m', $content)) {
        $key = 'base64:'.base64_encode(random_bytes(32));
        file_put_contents($env, preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $content), LOCK_EX);
    }
}

// Setelah ZIP versi baru diekstrak: buang cache bootstrap lama (rute/config/event) agar tidak memakai daftar lama.
$version = $base.'/VERSION';
if (is_file($version)) {
    foreach (glob($base.'/bootstrap/cache/{config,routes-v7,routes,events}.php', GLOB_BRACE) ?: [] as $cached) {
        if (filemtime($cached) < filemtime($version)) {
            @unlink($cached);
        }
    }
}

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath($base.'/public');

$app->handleRequest(Request::capture());
