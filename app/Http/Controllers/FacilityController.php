<?php

namespace App\Http\Controllers;

use App\Domain\Facilities\FacilityService;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\Supplier;
use App\Support\ExcelExport;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function __construct(private FacilityService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('sarana.lihat'), 403);

        return view('facilities.index', ['items' => $this->query($request)->get(), 'categories' => config('smm.facility_categories')]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('sarana.lihat'), 403);
        $rows = $this->query($request)->get()->map(fn (Facility $f) => [
            $f->number, config('smm.facility_categories')[$f->category] ?? $f->category, $f->name, $f->brand_model, $f->serial_number, $f->location,
            $f->quantity, Facility::CONDITIONS[$f->condition] ?? $f->condition, Format::date($f->acquired_at), Format::date($f->next_inspection_at),
            $f->needs_calibration ? Format::date($f->next_calibration_at) : '–', $f->status,
        ]);

        return ExcelExport::download('Daftar Sarana dan Prasarana', ['Nomor', 'Kategori', 'Nama', 'Merek/tipe', 'No. seri', 'Lokasi', 'Jumlah', 'Kondisi', 'Diperoleh', 'Pemeriksaan berikutnya', 'Kalibrasi berikutnya', 'Status'], $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('sarana.kelola'), 403);

        return view('facilities.form', ['f' => new Facility(['condition' => 'baik', 'quantity' => 1, 'status' => 'aktif']), 'categories' => config('smm.facility_categories'), 'maxMb' => Settings::get('upload.max_mb')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('sarana.kelola'), 403);
        $f = $this->service->create($request->user(), $this->validated($request, true), $request->file('photos', []));

        return redirect()->route('facilities.show', $f)->with('status', "{$f->number} ditambahkan.");
    }

    public function show(Request $request, Facility $facility)
    {
        abort_unless($request->user()->can('sarana.lihat'), 403);
        $facility->load(['attachments', 'logs.supplier', 'logs.performer', 'logs.attachments']);

        return view('facilities.show', ['f' => $facility, 'suppliers' => Supplier::where('status', '!=', 'tidak_dipakai')->orderBy('name')->get()]);
    }

    public function edit(Request $request, Facility $facility)
    {
        abort_unless($request->user()->can('sarana.kelola'), 403);

        return view('facilities.form', ['f' => $facility, 'categories' => config('smm.facility_categories'), 'maxMb' => Settings::get('upload.max_mb')]);
    }

    public function update(Request $request, Facility $facility)
    {
        abort_unless($request->user()->can('sarana.kelola'), 403);
        $this->service->update($request->user(), $facility, $this->validated($request, false));
        if ($request->hasFile('photos')) {
            $this->service->attach($facility, $facility, $request->file('photos', []), $request->user(), 'Foto');
        }

        return redirect()->route('facilities.show', $facility)->with('status', 'Perubahan disimpan.');
    }

    public function storeLog(Request $request, Facility $facility)
    {
        abort_unless($request->user()->can('sarana.kelola'), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FacilityLog::TYPES))],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'condition_after' => ['nullable', Rule::in(array_keys(Facility::CONDITIONS))],
            'result' => ['required', 'string', 'max:5000'],
            'cost' => ['nullable', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'valid_until' => ['nullable', 'date', 'after:performed_at', 'required_if:type,kalibrasi'],
            'evidence' => ['array', 'max:10'],
            'evidence.*' => ['file'],
        ], [], ['result' => 'hasil', 'valid_until' => 'berlaku s.d.']);
        $this->service->addLog($request->user(), $facility, collect($data)->except('evidence')->all(), $request->file('evidence', []));

        return back()->with('status', 'Catatan '.$data['type'].' disimpan.');
    }

    private function query(Request $request)
    {
        return Facility::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->string('condition')))
            ->when($request->query('jatuh_tempo'), fn ($q) => $q->where(fn ($w) => $w->whereDate('next_inspection_at', '<=', now()->addDays(14))->orWhere(fn ($c) => $c->where('needs_calibration', true)->whereDate('next_calibration_at', '<=', now()->addDays(14)))))
            ->when($request->query('status', 'aktif') !== 'semua', fn ($q) => $q->where('status', $request->query('status', 'aktif')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('number', 'like', '%'.$request->string('q').'%')->orWhere('location', 'like', '%'.$request->string('q').'%')))
            ->orderBy('number');
    }

    private function validated(Request $request, bool $creating): array
    {
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;
        $data = $request->validate(($creating ? ['category' => ['required', Rule::in(array_keys(config('smm.facility_categories')))]] : []) + [
            'name' => ['required', 'string', 'max:255'],
            'brand_model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(array_keys(Facility::CONDITIONS))],
            'acquired_at' => ['nullable', 'date'],
            'inspection_interval_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'next_inspection_at' => ['nullable', 'date'],
            'needs_calibration' => ['nullable', 'boolean'],
            'next_calibration_at' => ['nullable', 'date', 'required_if:needs_calibration,1'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['file', 'max:'.$max],
        ], [], ['name' => 'nama', 'next_calibration_at' => 'jadwal kalibrasi']);
        $data['needs_calibration'] = $request->boolean('needs_calibration');
        unset($data['photos']);

        return $data;
    }
}
