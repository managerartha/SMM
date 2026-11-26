<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setelah versi baru diunggah (berkas VERSION berbeda dari versi terpasang), migrasi database belum jalan.
 * Daripada error 500: GM/admin diarahkan ke Admin → Pembaruan Sistem, pengguna lain melihat pesan pemeliharaan.
 */
class RequireSystemUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::pending() || $request->routeIs('admin.system.*', 'login', 'logout', 'password.*', 'two-factor.*', 'install', 'install.run')) {
            return $next($request);
        }
        $user = $request->user();
        if ($user && $user->can('pengaturan.kelola')) {
            return redirect()->route('admin.system.index')->with('error', 'Versi baru terdeteksi ('.self::fileVersion().'). Jalankan pembaruan sistem dulu sebelum memakai SMM.');
        }

        return response()->view('updating', [], 503);
    }

    public static function pending(): bool
    {
        $file = self::fileVersion();
        if ($file === null || ! is_file(storage_path('app/installed.lock'))) {
            return false;
        }

        return $file !== self::installedVersion();
    }

    public static function fileVersion(): ?string
    {
        $v = @file_get_contents(base_path('VERSION'));

        return $v === false ? null : trim($v);
    }

    public static function installedVersion(): ?string
    {
        $v = @file_get_contents(storage_path('app/installed-version'));

        return $v === false ? null : trim($v);
    }

    public static function markInstalled(): void
    {
        if (($v = self::fileVersion()) !== null) {
            @file_put_contents(storage_path('app/installed-version'), $v);
        }
    }
}
