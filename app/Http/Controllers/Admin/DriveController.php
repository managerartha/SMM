<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Drive\DriveFolders;
use App\Domain\Drive\DriveGateway;
use App\Domain\Drive\GoogleOAuth;
use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class DriveController extends Controller
{
    public function __construct(private GoogleOAuth $oauth, private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.drive', [
            'driver' => config('smm.drive.driver'),
            'configured' => $this->oauth->isConfigured(),
            'connected' => $this->oauth->hasToken(),
            'email' => $this->oauth->hasToken() ? rescue(fn () => $this->oauth->connectedEmail(), null, false) : null,
            'redirect' => route('admin.drive.callback'),
            'health' => Settings::get('drive.last_health'),
            'pending' => StoredFile::where('state', StoredFile::PENDING)->count(),
            'failed' => StoredFile::where('state', StoredFile::FAILED)->get(),
            'stored' => StoredFile::where('state', StoredFile::STORED)->count(),
        ]);
    }

    public function connect(Request $request)
    {
        abort_unless($this->oauth->isConfigured(), 422, 'GOOGLE_DRIVE_CLIENT_ID / SECRET belum diisi di .env');
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->oauth->authUrl(route('admin.drive.callback'), $state));
    }

    public function callback(Request $request, DriveFolders $folders)
    {
        $expected = $request->session()->pull('google_oauth_state');
        abort_unless($expected && hash_equals($expected, (string) $request->query('state')), 403, 'State OAuth tidak cocok.');
        if ($request->query('error')) {
            return redirect()->route('admin.drive.index')->withErrors(['drive' => 'Izin Google ditolak: '.$request->query('error')]);
        }
        try {
            $info = $this->oauth->exchangeCode((string) $request->query('code'), route('admin.drive.callback'));
            $folders->forget();
            $this->log->log('pengaturan', 'Menghubungkan Google Drive '.($info['email'] ?? ''));
        } catch (Throwable $e) {
            return redirect()->route('admin.drive.index')->withErrors(['drive' => $e->getMessage()]);
        }

        return redirect()->route('admin.drive.index')->with('status', 'Google Drive terhubung'.($info['email'] ? ' ('.$info['email'].')' : '').'.');
    }

    public function test(DriveGateway $drive, DriveFolders $folders)
    {
        try {
            $info = $drive->health();
            $folders->idFor(config('smm.drive.root'), '');
            $folders->idFor(config('smm.drive.backup_root'), '');
            Settings::set('drive.last_health', ['ok' => true, 'at' => now()->toIso8601String(), 'account' => $info['account'] ?? null, 'quota' => $info['quota'] ?? null]);

            return back()->with('status', 'Koneksi berhasil: '.($info['account'] ?? 'OK').'. Folder "'.config('smm.drive.root').'" dan "'.config('smm.drive.backup_root').'" siap.');
        } catch (Throwable $e) {
            Settings::set('drive.last_health', ['ok' => false, 'at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return back()->withErrors(['drive' => $e->getMessage()]);
        }
    }

    public function disconnect(DriveFolders $folders)
    {
        $this->oauth->disconnect();
        $folders->forget();
        $this->log->log('pengaturan', 'Memutus koneksi Google Drive');

        return back()->with('status', 'Koneksi Google Drive diputus. Berkas di Drive tidak dihapus.');
    }
}
