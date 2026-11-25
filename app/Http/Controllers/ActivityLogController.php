<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->query($request)->paginate(50)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('activity.index', compact('logs', 'users'));
    }

    public function export(Request $request)
    {
        $rows = (function () use ($request) {
            foreach ($this->query($request)->limit(50000)->cursor() as $l) {
                yield [Format::dateTime($l->occurred_at), $l->user_name, $l->actionLabel(), $l->subject_label, $l->description, $l->ip, $l->hash];
            }
        })();

        return ExcelExport::download('Log Aktivitas SMM', ['Waktu', 'Pengguna', 'Aksi', 'Objek', 'Keterangan', 'IP', 'Hash'], $rows);
    }

    private function query(Request $request)
    {
        return ActivityLog::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('description', 'like', '%'.$request->string('q').'%')->orWhere('subject_label', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('id');
    }
}
