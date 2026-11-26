<?php

namespace App\Http\Controllers;

use App\Domain\Activity\ActivityLogger;
use App\Models\Division;
use App\Models\User;
use App\Support\EnvFile;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PDO;
use Throwable;

/**
 * Installer web sekali pakai (tanpa SSH, tanpa mengedit .env).
 * GM mengisi database, email, kata sandi backup, dan akun GM pertama; installer menulis .env,
 * membuat tabel, lalu mengunci diri (storage/app/installed.lock).
 *
 * Pengaman sebelum terpasang: database WAJIB di server yang sama (localhost) dengan kredensial yang valid,
 * sehingga hanya pemilik hosting yang bisa menyelesaikan instalasi. Bila SMM_INSTALL_TOKEN diisi, token juga diminta.
 */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        abort_if($this->installed(), 404);

        return view('install', [
            'checks' => $this->checks(),
            'tokenRequired' => (string) config('smm.install_token') !== '',
            'suggestedPassword' => Str::password(24, symbols: false),
        ]);
    }

    public function run(Request $request, ActivityLogger $log)
    {
        abort_if($this->installed(), 404);
        @set_time_limit(300);

        $data = $request->validate([
            'token' => [(string) config('smm.install_token') !== '' ? 'required' : 'nullable', 'string'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_password' => ['required', 'string', 'max:255'],
            'mail_username' => ['nullable', 'email', 'max:255'],
            'mail_password' => ['nullable', 'required_with:mail_username', 'string', 'max:255'],
            'backup_password' => ['required', 'string', 'min:12', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [], [
            'db_database' => 'nama database', 'db_username' => 'user database', 'db_password' => 'kata sandi database',
            'mail_username' => 'email pengirim', 'mail_password' => 'kata sandi email', 'backup_password' => 'kata sandi backup',
            'name' => 'nama', 'password' => 'kata sandi GM',
        ]);

        if ((string) config('smm.install_token') !== '' && ! hash_equals((string) config('smm.install_token'), (string) $data['token'])) {
            throw ValidationException::withMessages(['token' => 'Token instalasi salah.']);
        }

        // 1. Uji koneksi database (selalu localhost).
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $host = in_array($host, ['localhost', '127.0.0.1'], true) ? $host : 'localhost';
        try {
            new PDO("mysql:host={$host};dbname={$data['db_database']};charset=utf8mb4", $data['db_username'], $data['db_password'], [PDO::ATTR_TIMEOUT => 5]);
        } catch (Throwable $e) {
            if ($host === 'localhost') {
                try {
                    new PDO("mysql:host=127.0.0.1;dbname={$data['db_database']};charset=utf8mb4", $data['db_username'], $data['db_password'], [PDO::ATTR_TIMEOUT => 5]);
                    $host = '127.0.0.1';
                } catch (Throwable) {
                    throw ValidationException::withMessages(['db_database' => 'Tidak bisa terhubung ke database. Periksa nama database, user, dan kata sandi (pakai nama lengkap, mis. u123456789_smm).']);
                }
            } else {
                throw ValidationException::withMessages(['db_database' => 'Tidak bisa terhubung ke database: '.Str::limit($e->getMessage(), 150)]);
            }
        }

        // 2. Tulis .env
        $secure = $request->isSecure();
        $env = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $request->getSchemeAndHttpHost(),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'],
            'SMM_BACKUP_PASSWORD' => $data['backup_password'],
            'SESSION_SECURE_COOKIE' => $secure ? 'true' : 'false',
        ];
        if (! empty($data['mail_username'])) {
            $env += [
                'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtps', 'MAIL_HOST' => 'smtp.hostinger.com', 'MAIL_PORT' => '465',
                'MAIL_USERNAME' => $data['mail_username'], 'MAIL_PASSWORD' => $data['mail_password'],
                'MAIL_FROM_ADDRESS' => $data['mail_username'],
            ];
        }
        try {
            EnvFile::set(app()->environmentFilePath(), $env);
        } catch (Throwable $e) {
            return back()->withInput($request->except('password', 'password_confirmation', 'db_password', 'mail_password'))
                ->withErrors(['install' => 'Berkas .env tidak bisa ditulis. Ubah permission folder aplikasi ke 755 lalu coba lagi.']);
        }

        // 3. Terapkan ke proses ini, buat tabel, isi data dasar, buat akun GM.
        config([
            'database.connections.mysql.host' => $host,
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'],
            'database.default' => 'mysql',
            'smm.backup.password' => $data['backup_password'],
        ]);
        DB::purge('mysql');

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => BaseDataSeeder::class, '--force' => true]);
            if (User::exists()) {
                throw new \RuntimeException('Database ini sudah berisi pengguna SMM. Gunakan database kosong.');
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
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation', 'db_password', 'mail_password'))
                ->withErrors(['install' => 'Instalasi gagal: '.Str::limit($e->getMessage(), 300)]);
        }

        try {
            if (! app()->runningUnitTests()) {
                // Config & rute tidak di-cache supaya perubahan .env dan versi baru langsung berlaku.
                Artisan::call('view:cache');
            }
        } catch (Throwable) {
            // cache bisa dibuat belakangan lewat Admin → Pembaruan Sistem
        }

        // Ditampilkan langsung (bukan redirect) supaya kata sandi backup & perintah cron pasti terlihat sekali.
        return response()->view('install-done', [
            'backupPassword' => $data['backup_password'],
            'cron' => $this->cronCommand(),
            'email' => Str::lower($data['email']),
        ])->header('Cache-Control', 'no-store');
    }

    private function installed(): bool
    {
        if (is_file(storage_path('app/installed.lock'))) {
            return true;
        }
        try {
            return config('database.connections.mysql.database') && Schema::hasTable('users') && User::exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function cronCommand(): string
    {
        $php = '/usr/bin/php';
        foreach (['/opt/alt/php83/usr/bin/php', '/usr/local/bin/php'] as $candidate) {
            if (@is_file($candidate)) {
                $php = $candidate;
                break;
            }
        }

        return $php.' '.base_path('artisan').' schedule:run';
    }

    private function checks(): array
    {
        $writable = fn ($p) => is_dir($p) && is_writable($p);
        $envPath = app()->environmentFilePath();

        return [
            ['PHP versi 8.3 atau lebih baru (saat ini '.PHP_VERSION.')', version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['Ekstensi pdo_mysql', extension_loaded('pdo_mysql')],
            ['Ekstensi zip dengan enkripsi AES', class_exists(\ZipArchive::class) && \ZipArchive::isEncryptionMethodSupported(\ZipArchive::EM_AES_256)],
            ['Ekstensi fileinfo', extension_loaded('fileinfo')],
            ['Ekstensi mbstring', extension_loaded('mbstring')],
            ['Folder storage bisa ditulis', $writable(storage_path()) && $writable(storage_path('framework')) && $writable(storage_path('logs'))],
            ['Folder bootstrap/cache bisa ditulis', $writable(base_path('bootstrap/cache'))],
            ['Berkas .env bisa ditulis', is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath))],
            ['Kunci aplikasi (APP_KEY) sudah dibuat', (string) config('app.key') !== ''],
        ];
    }
}
