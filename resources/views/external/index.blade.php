@php use App\Support\Format; @endphp
<x-app title="Legalitas & Kontrak">
    <x-page-header title="Legalitas & Kontrak" subtitle="SKP PJK3, akta, NIB, domisili, sewa, SPK/kontrak klien">
        <a href="{{ route('external-documents.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('create', App\Models\ExternalDocument::class)<a href="{{ route('external-documents.create') }}" class="btn-primary">+ Tambah dokumen</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / judul / penerbit / nomor asli" class="input lg:col-span-2">
        <select name="category" class="input"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <div class="flex gap-2">
            <select name="status" class="input">
                <option value="">Semua status</option>
                @foreach (['berlaku' => 'Berlaku', 'segera' => 'Segera berakhir', 'kedaluwarsa' => 'Kedaluwarsa'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn-secondary">Saring</button>
        </div>
        @can('legalitas.kelola')<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="arsip" value="1" @checked(request('arsip')) class="rounded border-slate-300"> Tampilkan arsip</label>@endcan
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Judul / penerbit</th><th class="hidden md:table-cell">Nomor asli</th><th>Berakhir</th><th>Status</th><th class="hidden sm:table-cell">Verifikasi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($docs as $d)
                @php $v = $d->currentVersion; $st = $v?->validity(); @endphp
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('external-documents.show', $d) }}">{{ $d->number }}</a><div class="text-xs font-normal text-slate-500">{{ $d->category->name }}</div></td>
                    <td>{{ $d->title }}<div class="text-xs text-slate-500">{{ $d->issuer }}</div></td>
                    <td class="hidden md:table-cell">{{ $v?->original_number }}</td>
                    <td class="whitespace-nowrap">{{ $v?->expires_at ? Format::date($v->expires_at) : 'Tanpa masa berlaku' }}@if ($v?->expires_at && $v->daysLeft() >= 0)<div class="text-xs text-slate-500">{{ $v->daysLeft() }} hari lagi</div>@endif</td>
                    <td>@if ($d->isArchived())<x-badge color="red">Diarsip</x-badge>@elseif ($st)<x-badge :color="$st->badge()">{{ $st->label() }}</x-badge>@endif</td>
                    <td class="hidden sm:table-cell">@if ($v?->verified_at)<x-badge color="green">Terverifikasi</x-badge>@else<x-badge color="amber">Belum</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada dokumen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app>
