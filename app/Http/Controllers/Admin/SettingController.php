<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const PATTERNS = [
        'numbering.document' => 'Dokumen terkendali',
        'numbering.external_document' => 'Dokumen eksternal',
        'numbering.record' => 'Formulir terisi (Tahap 3)',
        'numbering.ncr' => 'NCR (Tahap 2)',
        'numbering.complaint' => 'Keluhan / feedback (Tahap 2)',
        'numbering.audit' => 'Audit internal (Tahap 4)',
        'numbering.personnel_ta' => 'Tenaga Ahli (Tahap 3)',
        'numbering.personnel_tt' => 'Tenaga Teknis (Tahap 3)',
        'numbering.facility' => 'Sarana & prasarana (Tahap 3)',
        'numbering.innovation' => 'Program inovasi (Tahap 4)',
    ];

    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.settings', ['s' => Settings::all(), 'patterns' => self::PATTERNS]);
    }

    public function update(Request $request)
    {
        $rules = [
            'upload_max_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'reminder_external_days' => ['required', 'string', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
            'reminder_external_soon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reminder_review_days' => ['required', 'integer', 'min:1', 'max:180'],
            'backup_daily_retention' => ['required', 'integer', 'min:7', 'max:365'],
            'backup_monthly_retention' => ['required', 'integer', 'min:3', 'max:120'],
            'patterns' => ['array'],
            'patterns.*' => ['required', 'string', 'max:100', 'regex:/\{urut(:\d)?\}/'],
        ];
        $data = $request->validate($rules, ['patterns.*.regex' => 'Setiap pola nomor wajib memuat {urut} atau {urut:N}.']);
        $uid = $request->user()->id;

        $values = [
            'upload.max_mb' => (int) $data['upload_max_mb'],
            'reminder.external_days' => collect(explode(',', $data['reminder_external_days']))->map(fn ($d) => (int) trim($d))->unique()->sortDesc()->values()->all(),
            'reminder.external_soon_days' => (int) $data['reminder_external_soon_days'],
            'reminder.review_days' => (int) $data['reminder_review_days'],
            'backup.daily_retention' => (int) $data['backup_daily_retention'],
            'backup.monthly_retention' => (int) $data['backup_monthly_retention'],
            'watermark.controlled_copy' => $request->boolean('watermark_controlled_copy'),
        ];
        foreach (array_keys(self::PATTERNS) as $key) {
            if (isset($data['patterns'][$key])) {
                $values[$key] = trim($data['patterns'][$key]);
            }
        }

        $changed = [];
        foreach ($values as $k => $v) {
            if (Settings::get($k) !== $v) {
                $changed[$k] = ['dari' => Settings::get($k), 'menjadi' => $v];
                Settings::set($k, $v, $uid);
            }
        }
        if ($changed) {
            $this->log->log('pengaturan', 'Mengubah pengaturan: '.implode(', ', array_keys($changed)), null, $changed);
        }

        return back()->with('status', $changed ? 'Pengaturan disimpan.' : 'Tidak ada perubahan.');
    }
}
