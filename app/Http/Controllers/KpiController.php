<?php

namespace App\Http\Controllers;

use App\Domain\Kpi\KpiCalculator;
use App\Domain\Kpi\KpiService;
use App\Models\Division;
use App\Models\Kpi;
use App\Models\User;
use App\Support\ExcelExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KpiController extends Controller
{
    public function __construct(private KpiService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('kpi.lihat'), 403);
        $year = $request->integer('year') ?: (int) now()->format('Y');
        $kpis = Kpi::with(['values', 'division', 'owner'])->where('year', $year)->where('is_active', true)->orderBy('code')->get();

        return view('kpis.index', ['kpis' => $kpis, 'year' => $year, 'years' => Kpi::distinct()->orderByDesc('year')->pluck('year')->push((int) now()->format('Y'))->unique()->sortDesc()]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('kpi.lihat'), 403);
        $year = $request->integer('year') ?: (int) now()->format('Y');
        $rows = Kpi::with('values')->where('year', $year)->orderBy('code')->get()->map(function (Kpi $k) use ($year) {
            $row = [$k->code, $k->name, $k->objective, $k->targetLabel()];
            for ($m = 1; $m <= 12; $m++) {
                $row[] = $k->values->firstWhere('period', sprintf('%d-%02d', $year, $m))?->value;
            }

            return $row;
        });

        return ExcelExport::download("Sasaran Mutu dan KPI {$year}", array_merge(['Kode', 'KPI', 'Sasaran mutu', 'Target'], ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']), $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('kpi.kelola'), 403);

        return view('kpis.form', ['kpi' => new Kpi(['year' => (int) now()->format('Y'), 'comparator' => '>=', 'unit' => '%', 'source' => 'manual', 'is_active' => true]), 'divisions' => Division::active()->orderBy('code')->get(), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('kpi.kelola'), 403);
        $kpi = $this->service->create($request->user(), $this->validated($request));

        return redirect()->route('kpis.show', $kpi)->with('status', "{$kpi->code} ditambahkan.");
    }

    public function show(Request $request, Kpi $kpi)
    {
        abort_unless($request->user()->can('kpi.lihat'), 403);
        $kpi->load(['values.enteredBy', 'division', 'owner']);

        return view('kpis.show', ['kpi' => $kpi, 'canEnter' => $this->service->canEnter($request->user(), $kpi)]);
    }

    public function edit(Request $request, Kpi $kpi)
    {
        abort_unless($request->user()->can('kpi.kelola'), 403);

        return view('kpis.form', ['kpi' => $kpi, 'divisions' => Division::active()->orderBy('code')->get(), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, Kpi $kpi)
    {
        abort_unless($request->user()->can('kpi.kelola'), 403);
        $this->service->update($request->user(), $kpi, $this->validated($request));

        return redirect()->route('kpis.show', $kpi)->with('status', 'Perubahan disimpan.');
    }

    public function storeValue(Request $request, Kpi $kpi)
    {
        $data = $request->validate(['period' => ['required', 'string'], 'value' => ['required', 'numeric'], 'note' => ['nullable', 'string', 'max:2000']], [], ['value' => 'realisasi', 'period' => 'periode']);
        $this->service->setValue($request->user(), $kpi, $data['period'], (float) $data['value'], $data['note'] ?? null);

        return back()->with('status', 'Realisasi disimpan.');
    }

    public function calculate(Request $request, KpiCalculator $calc)
    {
        abort_unless($request->user()->can('kpi.kelola'), 403);
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $n = $calc->run($data['period']);

        return back()->with('status', "{$n} realisasi KPI otomatis dihitung untuk {$data['period']}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:20'],
            'target' => ['required', 'numeric'],
            'comparator' => ['required', Rule::in(['>=', '<='])],
            'source' => ['required', Rule::in(array_keys(KpiCalculator::SOURCES))],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'nama KPI']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
