<?php

namespace App\Http\Controllers;

use App\Domain\Activity\ActivityLogger;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Installer web sekali pakai (tanpa SSH), mengikuti pola install.php di LMS:
 * cek persyaratan → buat tabel → isi data dasar → buat akun GM pertama → kunci installer.
 * Butuh SMM_INSTALL_TOKEN di .env; setelah selesai installer terkunci (storage/app/installed.lock).
 */
class InstallController extends Controller
{
    public function show()
    {
        abort_if($this->installed(), 404);

        return view('install', ['checks' => $this->checks(), 'tokenSet' => (string) config('smm.install_token') !== '']);
    }

    public function run(Request $request, ActivityLogger $log)
    {
        abort_if($this->installed(), 404);
        $token = (string) config('smm.install_token');
        abort_if(strlen($token) < 16, 403, 'SMM_INSTALL_TOKEN di .env belum diisi (minimal 16 karakter).');

        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        abort_unless(hash_equals($token, $data['token']), 403, 'Token instalasi salah.');

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => BaseDataSeeder::class, '--force' => true]);
            if (User::exists()) {
                abort(409, 'Sudah ada pengguna; instalasi dihentikan.');
            }
            $gm = DB::transaction(function () use ($data) {
                $gm = User::create([
                    'name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => $data['password'],
                    'position' => $data['position'] ?: 'General Manager', 'division_id' => Division::where('code', 'QA')->value('id'),
                ]);
                $gm->syncRoles(['gm']);

                return $gm;
            });
            file_put_contents(storage_path('app/installed.lock'), now()->toIso8601String());
            $log->log('sistem', 'Instalasi SMM selesai; akun GM pertama dibuat', $gm, [], $gm);
            Artisan::call('optimize');
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation', 'token'))->withErrors(['install' => 'Instalasi gagal: '.$e->getMessage()]);
        }

        return redirect()->route('login')->with('status', 'Instalasi selesai. Silakan masuk dengan akun GM. Kosongkan SMM_INSTALL_TOKEN di .env.');
    }

    private function installed(): bool
    {
        if (is_file(storage_path('app/installed.lock'))) {
            return true;
        }
        try {
            return Schema::hasTable('users') && User::exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function checks(): array
    {
        $dbOk = false;
        $dbMsg = '';
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (Throwable $e) {
            $dbMsg = $e->getMessage();
        }
        $writable = fn ($p) => is_dir($p) && is_writable($p);

        return [
            ['PHP versi ≥ 8.3 (saat ini '.PHP_VERSION.')', version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['Ekstensi pdo_mysql', extension_loaded('pdo_mysql')],
            ['Ekstensi zip (dengan enkripsi AES)', class_exists(\ZipArchive::class) && \ZipArchive::isEncryptionMethodSupported(\ZipArchive::EM_AES_256)],
            ['Ekstensi fileinfo', extension_loaded('fileinfo')],
            ['Ekstensi mbstring', extension_loaded('mbstring')],
            ['Folder storage bisa ditulis', $writable(storage_path()) && $writable(storage_path('framework')) && $writable(storage_path('logs'))],
            ['Folder bootstrap/cache bisa ditulis', $writable(base_path('bootstrap/cache'))],
            ['APP_KEY terisi', (string) config('app.key') !== ''],
            ['Koneksi database'.($dbMsg ? ': '.Str::limit($dbMsg, 120) : ''), $dbOk],
            ['SMM_BACKUP_PASSWORD terisi (≥ 12 karakter)', strlen((string) config('smm.backup.password')) >= 12],
            ['APP_DEBUG=false (wajib di server)', ! config('app.debug') || ! app()->isProduction()],
        ];
    }
}
