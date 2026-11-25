<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Backup\BackupService;
use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backup', [
            'runs' => BackupRun::with('triggeredBy')->orderByDesc('id')->paginate(30),
            'last' => BackupRun::where('status', 'berhasil')->orderByDesc('id')->first(),
            'passwordSet' => strlen((string) config('smm.backup.password')) >= 12,
        ]);
    }

    public function run(Request $request, BackupService $backup)
    {
        @set_time_limit(600);
        $run = $backup->run('manual', $request->user());

        return back()->with($run->status === 'berhasil' ? 'status' : 'error', $run->status === 'berhasil' ? "Backup berhasil: {$run->file_name}" : 'Backup gagal: '.$run->message);
    }
}
