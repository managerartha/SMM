<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ExternalDocument;
use App\Models\User;
use Illuminate\Http\Request;

/** Pencarian global: nomor, judul, ringkasan, nama. Hasil disaring sesuai hak akses. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();
        $results = ['documents' => collect(), 'external' => collect(), 'users' => collect(), 'complaints' => collect(), 'ncrs' => collect(), 'risks' => collect(),
            'classes' => collect(), 'records' => collect(), 'personnel' => collect(), 'facilities' => collect(), 'suppliers' => collect()];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

            if ($user->can('viewAny', Document::class)) {
                $docs = Document::with(['type', 'division', 'currentRevision', 'openRevision'])
                    ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('summary', 'like', $like))
                    ->when(! $user->can('dokumen.kelola'), fn ($x) => $x->notArchived())
                    ->orderBy('number')->limit(50)->get();
                $results['documents'] = $docs->filter(fn ($d) => $user->can('view', $d))->values();
            }
            if ($user->can('viewAny', ExternalDocument::class)) {
                $results['external'] = ExternalDocument::with(['category', 'currentVersion'])
                    ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('issuer', 'like', $like)
                        ->orWhereHas('versions', fn ($v) => $v->where('original_number', 'like', $like)))
                    ->when(! $user->can('legalitas.kelola'), fn ($x) => $x->notArchived())
                    ->orderBy('number')->limit(50)->get();
            }
            if ($user->can('viewAny', \App\Models\Complaint::class)) {
                $results['complaints'] = \App\Models\Complaint::visibleTo($user)
                    ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('description', 'like', $like)->orWhere('reporter_name', 'like', $like)->orWhere('reporter_org', 'like', $like)->orWhere('class_code', 'like', $like))
                    ->orderByDesc('id')->limit(30)->get();
            }
            $results['ncrs'] = \App\Models\Ncr::visibleTo($user)
                ->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('description', 'like', $like))
                ->orderByDesc('id')->limit(30)->get();
            if ($user->can('viewAny', \App\Models\Risk::class)) {
                $results['risks'] = \App\Models\Risk::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('process', 'like', $like))
                    ->orderBy('number')->limit(30)->get();
            }
            if ($user->can('kelas.lihat')) {
                $results['classes'] = \App\Models\TrainingClass::where(fn ($w) => $w->where('class_code', 'like', $like)->orWhere('program_name', 'like', $like)->orWhere('client', 'like', $like))->orderByDesc('start_date')->limit(30)->get();
            }
            if ($user->can('catatan.catat') || $user->can('catatan.lihat-semua')) {
                $results['records'] = \App\Models\QualityRecord::visibleTo($user)->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like))->orderByDesc('id')->limit(30)->get();
            }
            if ($user->can('personel.lihat')) {
                $results['personnel'] = \App\Models\Personnel::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)->orWhere('position', 'like', $like))->orderBy('number')->limit(30)->get();
            }
            if ($user->can('sarana.lihat')) {
                $results['facilities'] = \App\Models\Facility::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial_number', 'like', $like))->orderBy('number')->limit(30)->get();
            }
            if ($user->can('pemasok.lihat')) {
                $results['suppliers'] = \App\Models\Supplier::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like))->orderBy('name')->limit(30)->get();
            }
            if ($user->can('pengguna.kelola')) {
                $results['users'] = User::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))->limit(20)->get();
            }
        }

        return view('search', compact('q', 'results'));
    }
}
