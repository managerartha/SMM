@php use App\Support\Format; use App\Models\Innovation; @endphp
<x-app title="Program Inovasi Mutu">
    <x-page-header title="Program Inovasi Mutu" subtitle="Usulan → persetujuan (GM tanpa anggaran / Direktur dengan anggaran) → pelaksanaan → evaluasi hasil">
        <a href="{{ route('innovations.export') }}" class="btn-secondary">Ekspor Excel</a>
        @can('inovasi.usul')<a href="{{ route('innovations.create') }}" class="btn-primary">+ Usulkan inovasi</a>@endcan
    </x-page-header>
    <form class="mb-3 flex gap-2"><select name="status" class="input w-auto" onchange="this.form.submit()"><option value="">Semua status</option>@foreach (Innovation::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Judul</th><th>Pengusul</th><th>PIC</th><th>Anggaran</th><th>Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $i)
                <tr><td class="whitespace-nowrap"><a class="link" href="{{ route('innovations.show', $i) }}">{{ $i->number }}</a></td><td>{{ $i->title }}</td><td>{{ $i->proposer->name }}</td><td>{{ $i->pic?->name ?? '–' }}</td>
                    <td class="whitespace-nowrap">{{ $i->budget ? 'Rp '.number_format($i->budget, 0, ',', '.') : '–' }}</td><td><x-badge :color="$i->statusColor()">{{ Innovation::STATUSES[$i->status] }}</x-badge></td></tr>
            @empty <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada usulan inovasi.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</x-app>
