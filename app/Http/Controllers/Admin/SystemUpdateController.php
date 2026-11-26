<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Pembaruan sistem tanpa SSH: setelah ZIP versi baru diekstrak, GM menekan tombol ini
 * untuk menjalankan migrasi database, melengkapi data dasar, dan menyegarkan cache.
 */
class SystemUpdateController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        Artisan::call('migrate:status', ['--pending' => true]);
        $status = Artisan::output();

        return view('admin.system', [
            'status' => $status,
            'version' => \App\Http\Middleware\RequireSystemUpdate::installedVersion() ?? '–',
            'fileVersion' => \App\Http\Middleware\RequireSystemUpdate::fileVersion() ?? 'dev',
            'pending' => \App\Http\Middleware\RequireSystemUpdate::pending(),
        ]);
    }

    public function run()
    {
        $out = '';
        try {
            Artisan::call('down', ['--retry' => 30]);
            Artisan::call('migrate', ['--force' => true]);
            $out .= Artisan::output();
            Artisan::call('db:seed', ['--class' => BaseDataSeeder::class, '--force' => true]);
            $out .= Artisan::output();
            Artisan::call('optimize:clear');
            // Config & rute sengaja TIDAK di-cache supaya perubahan .env dan versi baru langsung berlaku.
            foreach (['view:cache'] as $cmd) {
                Artisan::call($cmd);
                $out .= Artisan::output();
            }
            \App\Http\Middleware\RequireSystemUpdate::markInstalled();
            $this->log->log('sistem', 'Menjalankan pembaruan sistem (migrasi + cache)', null, ['output' => mb_substr($out, 0, 3000)]);
        } catch (Throwable $e) {
            $out .= "\nGAGAL: ".$e->getMessage();
            $this->log->log('sistem', 'Pembaruan sistem GAGAL: '.$e->getMessage());
        } finally {
            Artisan::call('up');
        }

        return back()->with('output', $out)->with('status', 'Pembaruan selesai. Periksa keluaran di bawah.');
    }
}
