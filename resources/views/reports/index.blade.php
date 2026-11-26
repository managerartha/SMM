<x-app title="Laporan Kinerja Layanan">
    <x-page-header title="Laporan Kinerja Layanan" subtitle="Disusun otomatis dari data SMM; dilengkapi analisis lalu disahkan → PDF resmi" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Jenis</th><th>Periode</th><th>Status</th><th>PDF</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($items as $r)
                    <tr><td class="whitespace-nowrap"><a class="link" href="{{ route('reports.show', $r) }}">{{ $r->number }}</a></td><td>{{ ucfirst($r->type) }}</td><td>{{ $r->periodLabel() }}</td>
                        <td><x-badge :color="['draf' => 'slate', 'pengesahan' => 'purple', 'disahkan' => 'green'][$r->status] ?? 'slate'">{{ ucfirst($r->status) }}</x-badge></td>
                        <td>@if ($r->file)<a class="link" href="{{ route('attachments.download', $r->file->uuid) }}">Unduh</a>@else – @endif</td></tr>
                @empty <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada laporan.</td></tr> @endforelse
                </tbody>
            </table>
        </section>
        @can('laporan.kelola')
        <form method="post" action="{{ route('reports.store') }}" class="card card-body space-y-3" x-data="{ t: 'bulanan' }">@csrf
            <h2 class="font-semibold">Susun laporan</h2>
            <div><label class="label">Jenis</label><select name="type" x-model="t" class="input"><option value="bulanan">Bulanan</option><option value="tahunan">Tahunan</option></select></div>
            <div x-show="t === 'bulanan'"><label class="label">Bulan</label><input type="month" name="month" value="{{ now()->subMonth()->format('Y-m') }}" class="input" :disabled="t !== 'bulanan'"></div>
            <div x-cloak x-show="t === 'tahunan'"><label class="label">Tahun</label><input type="number" name="year" value="{{ now()->year }}" class="input" :disabled="t !== 'tahunan'"></div>
            <x-field-error name="month" /><x-field-error name="year" />
            <button class="btn-primary">Susun dari data SMM</button>
            <p class="help">Bila laporan periode itu sudah ada (belum disahkan), angkanya diperbarui.</p>
        </form>
        @endcan
    </div>
</x-app>
