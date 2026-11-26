@php use App\Support\Format; use App\Models\Ncr; @endphp
<x-app title="NCR & CAPA">
    <x-page-header title="NCR & CAPA" subtitle="Ketidaksesuaian → koreksi → akar masalah → tindakan korektif → pemantauan efektivitas → penutupan">
        <a href="{{ route('ncrs.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('create', Ncr::class)<a href="{{ route('ncrs.create') }}" class="btn-primary">+ NCR baru</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / judul / uraian" class="input lg:col-span-2">
        <select name="status" class="input">
            @foreach (['aktif' => 'Semua yang belum selesai', 'lewat' => 'Lewat target', 'semua' => 'Semua'] + Ncr::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status', 'aktif') === $k)>{{ $v }}</option>@endforeach
        </select>
        <div class="flex gap-2"><select name="division" class="input"><option value="">Semua divisi</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division') == $d->id)>{{ $d->code }}</option>@endforeach</select><button class="btn-secondary">Saring</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Judul</th><th class="hidden md:table-cell">Divisi</th><th class="hidden sm:table-cell">PIC</th><th>Target</th><th>Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $n)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('ncrs.show', $n) }}">{{ $n->number }}</a><div class="text-xs font-normal text-slate-500">{{ ucfirst($n->severity) }}</div></td>
                    <td>{{ $n->title }}<div class="text-xs text-slate-500">{{ Ncr::SOURCES[$n->source] ?? $n->source }}{{ $n->complaint ? ' · '.$n->complaint->number : '' }}</div></td>
                    <td class="hidden md:table-cell">{{ $n->division->code }}</td>
                    <td class="hidden sm:table-cell">{{ $n->pic->name }}</td>
                    <td class="whitespace-nowrap {{ $n->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($n->target_date) }}</td>
                    <td><x-badge :color="$n->statusColor()">{{ $n->statusLabel() }}</x-badge>@if ($n->reopened_count)<div class="text-xs text-slate-500">dibuka ulang {{ $n->reopened_count }}×</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Tidak ada NCR.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-app>
