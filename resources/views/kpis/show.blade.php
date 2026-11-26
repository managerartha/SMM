@php use App\Support\Format; use App\Domain\Kpi\KpiCalculator; @endphp
<x-app :title="$kpi->code">
    <x-page-header :title="$kpi->code.' – '.$kpi->name" :subtitle="'Target '.$kpi->targetLabel().' · '.$kpi->year.' · '.(KpiCalculator::SOURCES[$kpi->source][0] ?? $kpi->source)">
        @can('kpi.kelola')<a href="{{ route('kpis.edit', $kpi) }}" class="btn-secondary">Ubah</a>@endcan
    </x-page-header>
    @if ($kpi->objective)<p class="mb-4 text-sm text-slate-700">Sasaran mutu: <i>{{ $kpi->objective }}</i></p>@endif
    <section class="card card-body mb-5"><x-kpi-chart :kpi="$kpi" :height="220" /></section>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>Periode</th><th>Realisasi</th><th>Status</th><th>Keterangan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($kpi->values->sortByDesc('period') as $v)
                    <tr><td>{{ $v->period }}</td><td class="font-semibold">{{ $v->value }} {{ $kpi->unit }}</td>
                        <td><x-badge :color="$kpi->achieved($v->value) ? 'green' : 'red'">{{ $kpi->achieved($v->value) ? 'Tercapai' : 'Tidak tercapai' }}</x-badge></td>
                        <td class="text-xs">{{ $v->is_auto ? 'Otomatis' : ($v->enteredBy?->name ?? '') }}{{ $v->note && ! $v->is_auto ? ' – '.$v->note : '' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">Belum ada realisasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        @if ($canEnter)
        <form method="post" action="{{ route('kpis.values.store', $kpi) }}" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Isi realisasi</h2>
            <div><label class="label">Bulan</label><input type="month" name="period" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" min="{{ $kpi->year }}-01" max="{{ $kpi->year }}-12" required class="input"></div>
            <div><label class="label">Realisasi ({{ $kpi->unit }})</label><input type="number" step="0.01" name="value" required class="input"></div>
            <div><label class="label">Keterangan / sumber data</label><input name="note" class="input"></div>
            <button class="btn-primary">Simpan</button>
            @if ($kpi->source !== 'manual')<p class="help">KPI ini dihitung otomatis. Nilai manual akan menggantikan hasil otomatis untuk bulan tersebut.</p>@endif
        </form>
        @endif
    </div>
</x-app>
