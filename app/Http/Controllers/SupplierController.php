<?php

namespace App\Http\Controllers;

use App\Domain\Suppliers\SupplierService;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('pemasok.lihat'), 403);

        return view('suppliers.index', ['items' => $this->query($request)->get()]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('pemasok.lihat'), 403);
        $rows = $this->query($request)->get()->map(fn (Supplier $s) => [
            $s->number, $s->name, Supplier::CATEGORIES[$s->category] ?? $s->category, $s->contact_person, $s->phone, $s->email,
            Supplier::STATUSES[$s->status] ?? $s->status, $s->latestEvaluation?->period, $s->latestEvaluation?->average,
            SupplierEvaluation::DECISIONS[$s->latestEvaluation?->decision] ?? '', Format::date($s->latestEvaluation?->evaluated_at),
        ]);

        return ExcelExport::download('Daftar Pemasok Terseleksi', ['Nomor', 'Nama', 'Kategori', 'Kontak', 'Telepon', 'Email', 'Status', 'Periode evaluasi', 'Nilai rata-rata', 'Keputusan', 'Tanggal evaluasi'], $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('pemasok.kelola'), 403);

        return view('suppliers.form', ['s' => new Supplier(['status' => 'aktif'])]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('pemasok.kelola'), 403);
        $s = $this->service->create($request->user(), $this->validated($request));

        return redirect()->route('suppliers.show', $s)->with('status', "{$s->number} ditambahkan.");
    }

    public function show(Request $request, Supplier $supplier)
    {
        abort_unless($request->user()->can('pemasok.lihat'), 403);
        $supplier->load('evaluations.evaluator');

        return view('suppliers.show', ['s' => $supplier]);
    }

    public function edit(Request $request, Supplier $supplier)
    {
        abort_unless($request->user()->can('pemasok.kelola'), 403);

        return view('suppliers.form', ['s' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_unless($request->user()->can('pemasok.kelola'), 403);
        $this->service->update($request->user(), $supplier, $this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Perubahan disimpan.');
    }

    public function evaluate(Request $request, Supplier $supplier)
    {
        abort_unless($request->user()->can('pemasok.kelola'), 403);
        $score = ['required', 'integer', 'between:1,5'];
        $data = $request->validate([
            'period' => ['required', 'string', 'max:20'], 'quality' => $score, 'timeliness' => $score, 'price' => $score, 'service' => $score,
            'decision' => ['required', Rule::in(array_keys(SupplierEvaluation::DECISIONS))], 'notes' => ['nullable', 'string', 'max:5000'],
            'evaluated_at' => ['nullable', 'date'],
        ]);
        $e = $this->service->evaluate($request->user(), $supplier, $data);

        return back()->with('status', "Evaluasi disimpan (rata-rata {$e->average}).");
    }

    private function query(Request $request)
    {
        return Supplier::with('latestEvaluation')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('number', 'like', '%'.$request->string('q').'%')))
            ->orderBy('name');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Supplier::CATEGORIES))],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(Supplier::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['name' => 'nama']);
    }
}
