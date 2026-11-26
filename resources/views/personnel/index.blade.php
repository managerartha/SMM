@php use App\Models\Personnel; @endphp
<x-app title="Personel">
    <x-page-header title="Personel (Tenaga Ahli & Tenaga Teknis)" subtitle="Profil, CV, ijazah, sertifikat/lisensi K3, SK, kontrak – dengan pengingat masa berlaku">
        <a href="{{ route('personnel.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('personel.kelola')<a href="{{ route('personnel.create') }}" class="btn-primary">+ Personel</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / nomor / jabatan" class="input max-w-xs">
        <select name="type" class="input max-w-[14rem]"><option value="">TA & TT</option>@foreach (Personnel::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="trainer" value="1" @checked(request('trainer')) class="rounded border-slate-300"> Trainer saja</label>
        <select name="status" class="input max-w-[10rem]"><option value="aktif">Aktif</option><option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option><option value="semua" @selected(request('status') === 'semua')>Semua</option></select>
        <button class="btn-secondary">Saring</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Nama</th><th class="hidden md:table-cell">Jabatan</th><th>Berkas</th><th>Masa berlaku</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $p)
                @php $exp = $p->activeDocuments->filter(fn ($d) => $d->expires_at); $bad = $exp->filter(fn ($d) => $d->validity()->value === 'kedaluwarsa')->count(); $soon = $exp->filter(fn ($d) => $d->validity()->value === 'segera')->count(); @endphp
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('personnel.show', $p) }}">{{ $p->number }}</a></td>
                    <td>{{ $p->name }} @if ($p->is_trainer)<x-badge color="blue">Trainer</x-badge>@endif @if ($p->status !== 'aktif')<x-badge>Nonaktif</x-badge>@endif</td>
                    <td class="hidden md:table-cell">{{ $p->position }}</td>
                    <td>{{ $p->active_documents_count }}</td>
                    <td>@if ($bad)<x-badge color="red">{{ $bad }} kedaluwarsa</x-badge>@endif @if ($soon)<x-badge color="amber">{{ $soon }} segera berakhir</x-badge>@endif @if (! $bad && ! $soon)<x-badge color="green">OK</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada personel.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app>
