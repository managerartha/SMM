<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use DatabaseMigrations;

    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Tulis .env ke folder sementara, jangan menyentuh .env asli.
        $this->envDir = storage_path('framework/testing/env-'.getmypid());
        File::ensureDirectoryExists($this->envDir);
        File::put($this->envDir.'/.env', "APP_KEY=base64:abc\nDB_DATABASE=\nDB_PASSWORD=\n");
        $this->app->useEnvironmentPath($this->envDir);
        @unlink(storage_path('app/installed.lock'));
        config(['smm.install_token' => '']);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        File::deleteDirectory($this->envDir);
        parent::tearDown();
    }

    private function form(array $over = []): array
    {
        $db = config('database.connections.mysql');

        return $over + [
            'db_database' => $db['database'], 'db_username' => $db['username'], 'db_password' => $db['password'],
            'mail_username' => 'mutu@contoh.test', 'mail_password' => "sandi'email\$1",
            'backup_password' => 'kata sandi backup panjang #1',
            'name' => 'GM Baru', 'email' => 'GM@Contoh.test', 'position' => 'General Manager',
            'password' => 'RahasiaKuat123', 'password_confirmation' => 'RahasiaKuat123',
        ];
    }

    public function test_installer_menulis_env_membuat_gm_dan_mengunci_diri(): void
    {
        $this->get('/install')->assertOk()->assertSee('Nama database');
        $this->post('/install', $this->form())
            ->assertOk()->assertSee('Instalasi selesai')->assertSee('kata sandi backup panjang #1')->assertSee('schedule:run');

        $env = File::get($this->envDir.'/.env');
        $this->assertStringContainsString("SMM_BACKUP_PASSWORD='kata sandi backup panjang #1'", $env);
        $this->assertStringContainsString('MAIL_HOST=smtp.hostinger.com', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('MAIL_PASSWORD="sandi\'email$1"', $env);

        // Nilai yang ditulis terbaca kembali dengan benar oleh pembaca .env Laravel.
        $parsed = \Dotenv\Dotenv::parse($env);
        $this->assertSame("sandi'email\$1", $parsed['MAIL_PASSWORD']);
        $this->assertSame('kata sandi backup panjang #1', $parsed['SMM_BACKUP_PASSWORD']);

        $gm = User::where('email', 'gm@contoh.test')->first();
        $this->assertTrue($gm->hasRole('gm'));
        $this->assertFileExists(storage_path('app/installed.lock'));
        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->form())->assertNotFound();
    }

    public function test_kredensial_database_salah_ditolak(): void
    {
        $this->post('/install', $this->form(['db_password' => 'salah']))->assertSessionHasErrors('db_database');
        $this->assertSame(0, User::count());
    }
}
