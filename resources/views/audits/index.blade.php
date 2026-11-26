@php use App\Support\Format; use App\Models\Audit; @endphp
<x-app title="Audit Internal">
    <x-page-header title="Audit Internal" subtitle="Program audit tahunan → audit per proses → checklist & temuan (otomatis NCR) → laporan" />
    @if ($mine->isNotEmpty())
    <section class="card mb-5">
        <h2 class="border-b px-4 py-3 font-semibold">Audit yang Anda laksanakan</h2>
        <ul class="divide-y text-sm">@foreach ($mine as $a)<li class="flex justify-between px-4 py-2.5"><a class="link" href="{{ route('audits.show', $a) }}">{{ $a->number }} – {{ $a->process }} ({{ $a->division->code }})</a><span class="text-xs text-slate-500">{{ Format::date($a->planned_date) }}</span></li>@endforeach</ul>
    </section>
    @endif
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <h2 class="border-b px-4 py-3 font-semibold">Program audit</h2>
            <ul class="divide-y text-sm">
                @forelse ($programs as $p)
                    <li class="flex items-center justify-between px-4 py-2.5"><a class="link font-medium" href="{{ route('audits.programs.show', $p) }}">Program Audit {{ $p->year }}</a>
                        <span class="flex items-center gap-2 text-xs">{{ $p->audits_count }} audit <x-badge :color="['draf' => 'slate', 'pengesahan' => 'purple', 'disahkan' => 'green'][$p->status]">{{ ucfirst($p->status) }}</x-badge></span></li>
                @empty <li class="px-4 py-6 text-center text-slate-500">Belum ada program audit.</li> @endforelse
            </ul>
            @if ($upcoming->isNotEmpty())
            <h2 class="border-y px-4 py-3 font-semibold">Audit berjalan & mendatang</h2>
            <ul class="divide-y text-sm">@foreach ($upcoming as $a)<li class="flex justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('audits.show', $a) }}">{{ $a->number }} – {{ $a->process }}</a><span class="flex items-center gap-2 text-xs">{{ Format::date($a->planned_date) }} · {{ $a->auditor->name }} <x-badge :color="$a->statusColor()">{{ Audit::STATUSES[$a->status] }}</x-badge></span></li>@endforeach</ul>
            @endif
        </section>
        @can('audit.kelola')
        <form method="post" action="{{ route('audits.programs.store') }}" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Program audit baru</h2>
            <div><label class="label">Tahun</label><input type="number" name="year" value="{{ now()->year }}" required class="input"><x-field-error name="year" /></div>
            <div><label class="label">Tujuan</label><textarea name="objective" rows="2" class="input">Memastikan SMM sesuai ISO 9001:2015 & persyaratan PJK3 serta diterapkan secara efektif.</textarea></div>
            <div><label class="label">Ruang lingkup</label><textarea name="scope" rows="2" class="input">Seluruh proses: pemasaran, CS, operasional pelatihan, administrasi, SDM, pengadaan, mutu.</textarea></div>
            <button class="btn-primary">Buat program</button>
        </form>
        @endcan
    </div>
</x-app>
