<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $versionFile;
    private ?string $oldVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->versionFile = base_path('VERSION');
        $this->oldVersion = is_file($this->versionFile) ? file_get_contents($this->versionFile) : null;
        file_put_contents(storage_path('app/installed.lock'), 'x');
        file_put_contents(storage_path('app/installed-version'), 'v1');
        file_put_contents($this->versionFile, 'v2');
    }

    protected function tearDown(): void
    {
        $this->oldVersion === null ? @unlink($this->versionFile) : file_put_contents($this->versionFile, $this->oldVersion);
        @unlink(storage_path('app/installed.lock'));
        @unlink(storage_path('app/installed-version'));
        parent::tearDown();
    }

    public function test_versi_baru_mengarahkan_gm_ke_pembaruan_dan_pengguna_lain_melihat_pesan(): void
    {
        $gm = $this->makeUser('gm');
        // Login tetap bisa saat pembaruan tertunda (bug yang ditemukan di simulasi server)
        $this->get(route('login'))->assertOk();
        $this->post(route('login'), ['email' => $gm->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($gm);
        $this->get(route('dashboard'))->assertRedirect(route('admin.system.index'));
        $this->actingAs($gm)->get(route('dashboard'))->assertRedirect(route('admin.system.index'));
        $this->actingAs($gm)->get(route('admin.system.index'))->assertOk()->assertSee('belum dipasang');
        $this->actingAs($this->makeUser('staf', 'CS'))->get(route('dashboard'))->assertStatus(503)->assertSee('sedang diperbarui');

        $this->actingAs($gm)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.system.run'))->assertRedirect();
        $this->assertSame('v2', trim(file_get_contents(storage_path('app/installed-version'))));
        $this->actingAs($gm)->get(route('dashboard'))->assertOk();
    }
}
