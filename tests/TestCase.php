<?php

namespace Tests;

use App\Domain\Drive\DriveGateway;
use App\Domain\Drive\LocalDriveGateway;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $driveRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driveRoot = storage_path('framework/testing/drive-'.getmypid());
        File::deleteDirectory($this->driveRoot);
        config(['smm.drive.local_root' => $this->driveRoot]);
        $this->app->singleton(DriveGateway::class, fn () => new LocalDriveGateway($this->driveRoot));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->driveRoot);
        parent::tearDown();
    }

    protected function seedBase(): void
    {
        $this->seed(BaseDataSeeder::class);
    }

    protected function makeUser(string|array $roles, ?string $division = 'QA', array $attrs = []): User
    {
        $user = User::factory()->create($attrs + [
            'division_id' => $division ? Division::where('code', $division)->value('id') : null,
            'position' => is_array($roles) ? implode('/', $roles) : ucfirst($roles),
        ]);
        $user->syncRoles((array) $roles);

        return $user->fresh();
    }

    protected function pdf(string $text = 'Dokumen uji'): UploadedFile
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $text);
        $path = tempnam(sys_get_temp_dir(), 'smmt').'.pdf';
        $pdf->Output('F', $path);

        return new UploadedFile($path, 'uji.pdf', 'application/pdf', null, true);
    }
}
