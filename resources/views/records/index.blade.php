@php use App\Support\Format; @endphp
<x-app title="Catatan Mutu">
    <x-page-header title="Catatan Mutu" subtitle="Bukti pelaksanaan: formulir terisi, daftar hadir, laporan trainer, foto, notulen – dengan masa simpan">
        <a href="{{ route('records.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('catatan.catat')<a href="{{ route('records.create') }}" class="btn-primary">+ Catatan baru</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / judul / kode kelas" class="input lg:col-span-2">
        <select name="form" class="input"><option value="">Semua formulir</option>@foreach ($forms as $f)<option value="{{ $f->id }}" @selected(request('form') == $f->id)>{{ $f->number }} {{ $f->title }}</option>@endforeach</select>
        <select name="division" class="input"><option value="">Semua divisi</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division') == $d->id)>{{ $d->code }}</option>@endforeach</select>
        <div class="flex gap-2"><select name="status" class="input"><option value="aktif">Aktif</option><option value="lewat" @selected(request('status') === 'lewat')>Lewat masa simpan</option><option value="arsip" @selected(request('status') === 'arsip')>Diarsip</option></select><button class="btn-secondary">Saring</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Judul</th><th class="hidden md:table-cell">Kelas</th><th>Tanggal</th><th class="hidden sm:table-cell">Simpan s.d.</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $r)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('records.show', $r) }}">{{ $r->number }}</a><div class="text-xs font-normal text-slate-500">{{ $r->division->code }}</div></td>
                    <td>{{ $r->title }}@if ($r->form)<div class="text-xs text-slate-500">{{ $r->form->number }}</div>@endif</td>
                    <td class="hidden md:table-cell">@if ($r->trainingClass)<a class="link" href="{{ route('classes.show', $r->trainingClass) }}">{{ $r->trainingClass->class_code }}</a>@else – @endif</td>
                    <td class="whitespace-nowrap">{{ Format::date($r->activity_date) }}</td>
                    <td class="hidden whitespace-nowrap sm:table-cell {{ $r->isPastRetention() ? 'text-amber-700 font-semibold' : '' }}">{{ Format::date($r->retention_until) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada catatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-app>
