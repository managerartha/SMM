@php use App\Support\Format; use App\Models\Supplier; use App\Models\SupplierEvaluation; @endphp
<x-app title="Pemasok">
    <x-page-header title="Pemasok" subtitle="Daftar pemasok terseleksi & evaluasi berkala (ISO 9001 klausul 8.4)">
        <a href="{{ route('suppliers.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('pemasok.kelola')<a href="{{ route('suppliers.create') }}" class="btn-primary">+ Pemasok</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / nomor" class="input max-w-xs">
        <select name="category" class="input max-w-[14rem]"><option value="">Semua kategori</option>@foreach (Supplier::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="status" class="input max-w-[14rem]"><option value="">Semua status</option>@foreach (Supplier::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn-secondary">Saring</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Nama</th><th class="hidden md:table-cell">Kategori</th><th>Status</th><th>Evaluasi terakhir</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $s)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('suppliers.show', $s) }}">{{ $s->number }}</a></td>
                    <td>{{ $s->name }}<div class="text-xs text-slate-500">{{ $s->contact_person }} {{ $s->phone }}</div></td>
                    <td class="hidden md:table-cell">{{ Supplier::CATEGORIES[$s->category] ?? $s->category }}</td>
                    <td><x-badge :color="['aktif' => 'green', 'dievaluasi' => 'amber', 'tidak_dipakai' => 'slate'][$s->status]">{{ Supplier::STATUSES[$s->status] }}</x-badge></td>
                    <td class="whitespace-nowrap text-xs">@if ($s->latestEvaluation){{ $s->latestEvaluation->period }} · <b>{{ number_format($s->latestEvaluation->average, 2, ',', '.') }}</b> · {{ SupplierEvaluation::DECISIONS[$s->latestEvaluation->decision] }}@else <span class="text-amber-700">Belum dievaluasi</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada pemasok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app>
