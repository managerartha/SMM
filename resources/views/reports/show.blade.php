@php use App\Support\Format; $d = $r->data; $edit = $r->status === 'draf' && auth()->user()->can('laporan.kelola'); @endphp
<x-app :title="$r->number">
    <x-page-header :title="'Laporan Kinerja '.$r->periodLabel()" :subtitle="$r->number.' · '.Format::date($d['period']['from']).' – '.Format::date($d['period']['to']).' · pengesah: '.$approverRole">
        <x-badge :color="['draf' => 'slate', 'pengesahan' => 'purple', 'disahkan' => 'green'][$r->status] ?? 'slate'">{{ ucfirst($r->status) }}</x-badge>
        @if ($edit)<form method="post" action="{{ route('reports.regenerate', $r) }}">@csrf<button class="btn-secondary">Perbarui angka</button></form>@endif
    </x-page-header>
    <div class="mb-4">@include('partials.approved', ['m' => $r])</div>
    @if ($canApprove)<div class="mb-5 max-w-xl">@include('partials.decide', ['action' => route('reports.decide', $r), 'title' => 'Pengesahan laporan'])</div>@endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Kelas selesai', $d['classes']['count']], ['Peserta', $d['classes']['participants']], ['Kelulusan', ($d['classes']['pass_pct'] ?? '–').'%'], ['Keluhan', $d['complaints']['keluhan']],
            ['Tanggapan tepat waktu', ($d['complaints']['ontime_pct'] ?? '–').'%'], ['NCR baru / ditutup', $d['ncr']['new'].' / '.$d['ncr']['closed']], ['NCR lewat target', $d['ncr']['overdue']], ['Audit selesai', $d['audits']['done']]] as [$l, $v])
            <div class="card card-body"><p class="text-xs text-slate-500">{{ $l }}</p><p class="text-xl font-semibold">{{ $v }}</p></div>
        @endforeach
    </div>
    <section class="card mb-5 overflow-x-auto">
        <h2 class="border-b px-4 py-3 font-semibold">Sasaran mutu (target vs realisasi)</h2>
        <table class="table"><thead><tr><th>Kode</th><th>KPI</th><th>Target</th><th>Realisasi</th><th>Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($d['kpis'] as $k)
                <tr><td>{{ $k['code'] }}</td><td>{{ $k['name'] }}</td><td>{{ $k['target'] }}</td><td>{{ $k['average'] ?? '–' }} {{ $k['average'] !== null ? $k['unit'] : '' }}@if ($k['months'] > 1) <span class="text-xs text-slate-500">({{ $k['months_met'] }}/{{ $k['months'] }} bln)</span>@endif</td>
                    <td>@if ($k['achieved'] === null)<x-badge color="slate">Belum ada data</x-badge>@elseif ($k['achieved'])<x-badge color="green">Tercapai</x-badge>@else<x-badge color="red">Tidak tercapai</x-badge>@endif</td></tr>
            @empty <tr><td colspan="5" class="py-4 text-center text-slate-500">Belum ada KPI.</td></tr> @endforelse
            </tbody></table>
    </section>
    <section class="card mb-5 overflow-x-auto">
        <h2 class="border-b px-4 py-3 font-semibold">Kepuasan pengguna layanan</h2>
        <table class="table"><thead><tr><th>Survei</th><th>Respons</th><th>Rata-rata</th><th>% puas</th><th>Negatif</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@foreach ($d['surveys'] as $s)<tr><td>{{ $s['label'] }}</td><td>{{ $s['count'] }}</td><td>{{ $s['average'] ?? '–' }}</td><td>{{ $s['satisfied_pct'] ?? '–' }}</td><td>{{ $s['negative'] }}</td></tr>@endforeach</tbody></table>
    </section>
    <section class="card card-body">
        <h2 class="mb-2 font-semibold">Analisis & tindak lanjut</h2>
        @if ($edit)
            <form method="post" action="{{ route('reports.analysis', $r) }}" class="space-y-2">@csrf
                <textarea name="analysis" rows="6" class="input" placeholder="Analisis pencapaian, penyebab KPI tidak tercapai, dan rencana tindak lanjut">{{ old('analysis', $r->analysis) }}</textarea>
                <x-field-error name="analysis" /><button class="btn-secondary btn-sm">Simpan analisis</button>
            </form>
            <form method="post" action="{{ route('reports.submit', $r) }}" class="mt-3">@csrf<button class="btn-primary">Ajukan untuk pengesahan</button></form>
        @else
            <p class="whitespace-pre-line text-sm">{{ $r->analysis ?: '–' }}</p>
        @endif
    </section>
</x-app>
