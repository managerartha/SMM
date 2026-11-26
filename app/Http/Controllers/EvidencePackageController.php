<?php

namespace App\Http\Controllers;

use App\Jobs\BuildEvidencePackage;
use App\Models\EvidenceCategory;
use App\Models\EvidencePackage;
use Illuminate\Http\Request;

class EvidencePackageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('paket.unduh') || $request->user()->can('paket.buat'), 403);

        return view('evidence.index', ['packages' => EvidencePackage::with(['creator', 'file'])->latest()->limit(30)->get(), 'categories' => EvidenceCategory::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('paket.buat'), 403);
        $d = $request->validate(['date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from']]);
        if (EvidencePackage::whereIn('status', ['antri', 'proses'])->exists()) {
            return back()->with('error', 'Masih ada Paket Bukti yang sedang dibuat. Tunggu sampai selesai.');
        }
        $pkg = EvidencePackage::create($d + ['status' => 'antri', 'created_by' => $request->user()->id]);
        BuildEvidencePackage::dispatch($pkg->id);

        return back()->with('status', 'Paket Bukti sedang dibuat di latar belakang (beberapa menit). Anda akan menerima notifikasi bila selesai.');
    }
}
