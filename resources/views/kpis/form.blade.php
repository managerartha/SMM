@php use App\Domain\Kpi\KpiCalculator; $editing = $kpi->exists; @endphp
<x-app :title="$editing ? 'Ubah '.$kpi->code : 'KPI baru'">
    <x-page-header :title="$editing ? 'Ubah '.$kpi->code : 'Sasaran mutu / KPI baru'" />
    <form method="post" action="{{ $editing ? route('kpis.update', $kpi) : route('kpis.store') }}" class="card card-body max-w-3xl space-y-4">@csrf @if ($editing) @method('PUT') @endif
        <div><label class="label">Nama KPI *</label><input name="name" value="{{ old('name', $kpi->name) }}" required class="input" placeholder="Mis. Kepuasan peserta pelatihan"></div>
        <div><label class="label">Pernyataan sasaran mutu</label><textarea name="objective" rows="2" class="input" placeholder="Mis. Rata-rata kepuasan peserta minimal 4,2 dari 5 setiap bulan">{{ old('objective', $kpi->objective) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div><label class="label">Pembanding</label><select name="comparator" class="input"><option value=">=">≥ (minimal)</option><option value="<=" @selected(old('comparator', $kpi->comparator) === '<=')>≤ (maksimal)</option></select></div>
            <div><label class="label">Target *</label><input type="number" step="0.01" name="target" value="{{ old('target', $kpi->target) }}" required class="input"></div>
            <div><label class="label">Satuan</label><input name="unit" value="{{ old('unit', $kpi->unit) }}" required class="input"></div>
            <div><label class="label">Tahun</label><input type="number" name="year" value="{{ old('year', $kpi->year) }}" required class="input"></div>
        </div>
        <div><label class="label">Sumber realisasi</label><select name="source" class="input">@foreach (KpiCalculator::SOURCES as $k => [$label, $unit])<option value="{{ $k }}" @selected(old('source', $kpi->source) === $k)>{{ $label }}{{ $unit ? ' ['.$unit.']' : '' }}</option>@endforeach</select>
            <p class="help">Otomatis = dihitung tiap hari dari data SMM (survei, keluhan, NCR, kelas). Manual = diisi per bulan (mis. respons CS dari sistem leads).</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Divisi</label><select name="division_id" class="input"><option value="">–</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', $kpi->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
            <div><label class="label">Penanggung jawab</label><select name="owner_id" class="input"><option value="">–</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('owner_id', $kpi->owner_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $kpi->is_active)) class="rounded border-slate-300"> Aktif</label>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ $editing ? route('kpis.show', $kpi) : route('kpis.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
