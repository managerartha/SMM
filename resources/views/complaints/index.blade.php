@php use App\Support\Format; use App\Models\Complaint; @endphp
<x-app title="Keluhan & Feedback">
    <x-page-header title="Keluhan & Feedback" subtitle="Keluhan, apresiasi, dan saran dari peserta, klien, internal, maupun survei">
        <a href="{{ route('complaints.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('create', Complaint::class)<a href="{{ route('complaints.create') }}" class="btn-primary">+ Catat baru</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / perihal / pelapor / kode kelas" class="input lg:col-span-2">
        <select name="type" class="input"><option value="">Semua jenis</option>@foreach (Complaint::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="status" class="input"><option value="">Semua status</option>
            @foreach (['terbuka' => 'Belum selesai', 'lewat' => 'Lewat batas tanggapan'] + Complaint::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
        <div class="flex gap-2"><select name="division" class="input"><option value="">Semua divisi</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division') == $d->id)>{{ $d->code }}</option>@endforeach</select><button class="btn-secondary">Saring</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Perihal</th><th class="hidden md:table-cell">Pelapor</th><th class="hidden sm:table-cell">Diterima</th><th>Status</th><th class="hidden lg:table-cell">PIC</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $c)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('complaints.show', $c) }}">{{ $c->number }}</a><div class="text-xs font-normal text-slate-500">{{ Complaint::TYPES[$c->type] }}</div></td>
                    <td>{{ $c->subject }}<div class="text-xs text-slate-500">{{ Complaint::CATEGORIES[$c->category] ?? '' }}{{ $c->class_code ? ' · '.$c->class_code : '' }}</div></td>
                    <td class="hidden md:table-cell">{{ $c->reporter_name ?? '–' }}<div class="text-xs text-slate-500">{{ $c->reporter_org }}</div></td>
                    <td class="hidden whitespace-nowrap sm:table-cell">{{ Format::date($c->received_at) }}</td>
                    <td><x-badge :color="$c->statusColor()">{{ $c->statusLabel() }}</x-badge>@if ($c->isOverdue()) <x-badge color="red">Lewat batas</x-badge>@endif @if ($c->ncr)<div class="mt-0.5 text-xs"><a class="link" href="{{ route('ncrs.show', $c->ncr) }}">{{ $c->ncr->number }}</a></div>@endif</td>
                    <td class="hidden lg:table-cell">{{ $c->pic?->name ?? '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada catatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-app>
