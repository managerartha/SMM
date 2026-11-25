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
        $results = ['documents' => collect(), 'external' => collect(), 'users' => collect()];

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
            if ($user->can('pengguna.kelola')) {
                $results['users'] = User::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))->limit(20)->get();
            }
        }

        return view('search', compact('q', 'results'));
    }
}
