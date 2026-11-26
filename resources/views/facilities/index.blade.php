@php use App\Support\Format; use App\Models\Facility; @endphp
<x-app title="Sarana & Prasarana">
    <x-page-header title="Sarana & Prasarana" subtitle="Inventaris, kondisi, jadwal pemeriksaan/kalibrasi, riwayat perbaikan">
        <a href="{{ route('facilities.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('sarana.kelola')<a href="{{ route('facilities.create') }}" class="btn-primary">+ Sarana</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / nomor / lokasi" class="input max-w-xs">
        <select name="category" class="input max-w-[14rem]"><option value="">Semua kategori</option>@foreach ($categories as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $k }} – {{ $v }}</option>@endforeach</select>
        <select name="condition" class="input max-w-[12rem]"><option value="">Semua kondisi</option>@foreach (Facility::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected(request('condition') === $k)>{{ $v }}</option>@endforeach</select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="jatuh_tempo" value="1" @checked(request('jatuh_tempo')) class="rounded border-slate-300"> Jatuh tempo ≤ 14 hari</label>
        <button class="btn-secondary">Saring</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Nama</th><th class="hidden md:table-cell">Lokasi</th><th>Jml</th><th>Kondisi</th><th class="hidden sm:table-cell">Pemeriksaan / kalibrasi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $f)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('facilities.show', $f) }}">{{ $f->number }}</a></td>
                    <td>{{ $f->name }}<div class="text-xs text-slate-500">{{ $f->brand_model }}</div></td>
                    <td class="hidden md:table-cell">{{ $f->location ?? '–' }}</td>
                    <td>{{ $f->quantity }}</td>
                    <td><x-badge :color="$f->conditionColor()">{{ Facility::CONDITIONS[$f->condition] }}</x-badge></td>
                    <td class="hidden whitespace-nowrap text-xs sm:table-cell"><span class="{{ $f->isInspectionDue() ? 'font-semibold text-red-600' : '' }}">{{ $f->next_inspection_at ? 'Periksa '.Format::date($f->next_inspection_at) : '' }}</span>@if ($f->needs_calibration)<br><span class="{{ $f->isCalibrationDue() ? 'font-semibold text-red-600' : '' }}">Kalibrasi {{ Format::date($f->next_calibration_at) }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app>
