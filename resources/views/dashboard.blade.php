@php use App\Support\Format; @endphp
<x-app title="Dashboard">
    <x-page-header title="Selamat datang, {{ auth()->user()->name }}" subtitle="{{ Format::date(now()) }} · {{ auth()->user()->roleLabels() }}" />

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card card-body"><p class="text-xs text-slate-500">Dokumen berlaku</p><p class="mt-1 text-2xl font-semibold">{{ $stats['berlaku'] }}</p></div>
        @if (! is_null($stats['proses']))<div class="card card-body"><p class="text-xs text-slate-500">Revisi dalam proses</p><p class="mt-1 text-2xl font-semibold">{{ $stats['proses'] }}</p></div>@endif
        @if (! is_null($stats['legalitas']))<div class="card card-body"><p class="text-xs text-slate-500">Legalitas & kontrak</p><p class="mt-1 text-2xl font-semibold">{{ $stats['legalitas'] }}</p></div>@endif
        @php $pending = $toReview->count() + $toApprove->count() + $myActions->count() + $toVerify->count() + $checksDue->count() + $toClose->count() + $myComplaints->count(); @endphp
        <div class="card card-body"><p class="text-xs text-slate-500">Menunggu tindakan saya</p><p class="mt-1 text-2xl font-semibold {{ $pending ? 'text-red-600' : '' }}">{{ $pending }}</p></div>
    </div>
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <a href="{{ route('ncrs.index') }}" class="card card-body hover:ring-brand-600"><p class="text-xs text-slate-500">NCR terbuka</p><p class="mt-1 text-2xl font-semibold">{{ $qms['ncr_open'] }}</p></a>
        <a href="{{ route('ncrs.index', ['status' => 'lewat']) }}" class="card card-body hover:ring-brand-600"><p class="text-xs text-slate-500">NCR lewat target</p><p class="mt-1 text-2xl font-semibold {{ $qms['ncr_overdue'] ? 'text-red-600' : '' }}">{{ $qms['ncr_overdue'] }}</p></a>
        @if (! is_null($qms['complaints_open']))<a href="{{ route('complaints.index', ['status' => 'terbuka']) }}" class="card card-body hover:ring-brand-600"><p class="text-xs text-slate-500">Keluhan belum selesai</p><p class="mt-1 text-2xl font-semibold">{{ $qms['complaints_open'] }}@if ($qms['complaints_overdue'])<span class="ml-1 text-sm text-red-600">({{ $qms['complaints_overdue'] }} lewat batas)</span>@endif</p></a>@endif
        @if (! is_null($qms['risks_high']))<a href="{{ route('risks.index', ['level' => 'tinggi']) }}" class="card card-body hover:ring-brand-600"><p class="text-xs text-slate-500">Risiko level tinggi</p><p class="mt-1 text-2xl font-semibold {{ $qms['risks_high'] ? 'text-red-600' : '' }}">{{ $qms['risks_high'] }}</p></a>@endif
    </div>

    @php $opsCards = array_filter([
        ['Kelas bulan ini', $ops['classes_month'], route('classes.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]), false],
        ['Kepuasan peserta (90 hari)', $ops['survey_avg'] !== null ? $ops['survey_avg'].' / 5' : null, route('surveys.index'), false],
        ['Berkas personel ≤ 90 hari berakhir', $ops['personnel_expiring'], route('personnel.index'), (bool) $ops['personnel_expiring']],
        ['Sarana jatuh tempo periksa/kalibrasi', $ops['facilities_due'], route('facilities.index', ['jatuh_tempo' => 1]), (bool) $ops['facilities_due']],
    ], fn ($c) => $c[1] !== null); @endphp
    @if ($opsCards)
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($opsCards as [$label, $val, $url, $warn])
            <a href="{{ $url }}" class="card card-body hover:ring-brand-600"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold {{ $warn ? 'text-amber-700' : '' }}">{{ $val }}</p></a>
        @endforeach
    </div>
    @endif

    @if ($kpiStatus->isNotEmpty())
    <section class="card mb-5">
        <h2 class="flex items-center justify-between border-b px-4 py-3 text-sm font-semibold">Sasaran mutu & KPI (realisasi terakhir) <a class="link text-xs font-normal" href="{{ route('kpis.index') }}">Lihat semua</a></h2>
        <div class="grid gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($kpiStatus as ['kpi' => $k, 'value' => $v])
                <a href="{{ route('kpis.show', $k) }}" class="flex items-center justify-between gap-2 bg-white px-4 py-3 text-sm hover:bg-slate-50">
                    <span><span class="font-medium">{{ $k->name }}</span><span class="block text-xs text-slate-500">Target {{ $k->targetLabel() }}</span></span>
                    @if ($v !== null)<x-badge :color="$k->achieved($v) ? 'green' : 'red'">{{ $v }} {{ $k->unit }}</x-badge>@else<x-badge>Belum ada</x-badge>@endif
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Menunggu tindakan saya</h2>
            <ul class="divide-y text-sm">
                @foreach ($toApprove as $r)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('documents.show', $r->document) }}">{{ $r->fullLabel() }} – {{ $r->document->title }}</a><x-badge color="purple">Sahkan</x-badge></li>
                @endforeach
                @foreach ($toReview as $r)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('documents.show', $r->document) }}">{{ $r->fullLabel() }} – {{ $r->document->title }}</a><x-badge color="blue">Review</x-badge></li>
                @endforeach
                @foreach ($myDrafts as $r)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('documents.show', $r->document) }}">{{ $r->fullLabel() }} – {{ $r->document->title }}</a><x-badge>Draf saya</x-badge></li>
                @endforeach
                @foreach ($toClose as $n)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('ncrs.show', $n) }}">{{ $n->number }} – {{ $n->title }}</a><x-badge color="purple">Tutup NCR</x-badge></li>
                @endforeach
                @foreach ($toVerify as $n)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('ncrs.show', $n) }}">{{ $n->number }} – {{ $n->title }}</a><x-badge color="blue">Verifikasi tindakan</x-badge></li>
                @endforeach
                @foreach ($checksDue as $c)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('ncrs.show', $c->ncr) }}">{{ $c->ncr->number }} – efektivitas hari ke-{{ $c->day }}</a><span class="text-xs {{ $c->due_date->isPast() ? 'font-semibold text-red-600' : 'text-slate-500' }}">{{ Format::date($c->due_date) }}</span></li>
                @endforeach
                @foreach ($myActions as $a)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('ncrs.show', $a->ncr) }}">{{ $a->ncr->number }} – {{ \Illuminate\Support\Str::limit($a->description, 60) }}</a><span class="text-xs {{ $a->isOverdue() ? 'font-semibold text-red-600' : 'text-slate-500' }}">Tindakan · {{ Format::date($a->due_date) }}</span></li>
                @endforeach
                @foreach ($myComplaints as $c)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5"><a class="link" href="{{ route('complaints.show', $c) }}">{{ $c->number }} – {{ $c->subject }}</a><x-badge color="amber">Tanggapi</x-badge></li>
                @endforeach
                @if ($pending === 0 && $myDrafts->isEmpty())
                    <li class="px-4 py-6 text-center text-slate-500">Tidak ada yang menunggu. 👍</li>
                @endif
            </ul>
        </section>

        <section class="card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Jadwal tinjauan dokumen (≤ 30 hari)</h2>
            <ul class="divide-y text-sm">
                @forelse ($reviewDue as $d)
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5">
                        <a class="link" href="{{ route('documents.show', $d) }}">{{ $d->number }} – {{ $d->title }}</a>
                        <span class="whitespace-nowrap text-xs {{ $d->next_review_date->isPast() ? 'text-red-600 font-semibold' : 'text-slate-500' }}">{{ Format::date($d->next_review_date) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Tidak ada tinjauan dalam 30 hari ke depan.</li>
                @endforelse
            </ul>
        </section>

        @if (! is_null($stats['legalitas']))
        <section class="card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Legalitas segera berakhir / kedaluwarsa</h2>
            <ul class="divide-y text-sm">
                @forelse ($external as $d)
                    @php $v = $d->currentVersion; $st = $v->validity(); @endphp
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5">
                        <a class="link" href="{{ route('external-documents.show', $d) }}">{{ $d->number }} – {{ $d->title }}</a>
                        <span class="flex items-center gap-2 whitespace-nowrap text-xs"><span>{{ Format::date($v->expires_at) }}</span><x-badge :color="$st->badge()">{{ $st->label() }}</x-badge></span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Semua legalitas masih berlaku lebih dari 90 hari.</li>
                @endforelse
            </ul>
        </section>
        @endif

        @can('backup.kelola')
        <section class="card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Status sistem</h2>
            <dl class="divide-y text-sm">
                <div class="flex justify-between px-4 py-2.5"><dt>Backup terakhir</dt><dd>
                    @if ($backup)
                        <x-badge :color="$backup->status === 'berhasil' ? 'green' : ($backup->status === 'gagal' ? 'red' : 'blue')">{{ ucfirst($backup->status) }}</x-badge>
                        <span class="text-xs text-slate-500">{{ Format::dateTime($backup->started_at) }}</span>
                    @else <x-badge color="amber">Belum pernah</x-badge> @endif
                </dd></div>
                <div class="flex justify-between px-4 py-2.5"><dt>Google Drive</dt><dd>
                    @if ($driveHealth)
                        <x-badge :color="$driveHealth['ok'] ? 'green' : 'red'">{{ $driveHealth['ok'] ? 'Terhubung' : 'Bermasalah' }}</x-badge>
                    @else <x-badge color="amber">Belum diperiksa</x-badge> @endif
                </dd></div>
                <div class="flex justify-between px-4 py-2.5"><dt>Berkas menunggu pindah ke Drive</dt><dd class="{{ $pendingFiles ? 'font-semibold text-amber-700' : '' }}">{{ $pendingFiles }}</dd></div>
            </dl>
        </section>
        @endcan

        @if ($activity->isNotEmpty())
        <section class="card lg:col-span-2">
            <h2 class="flex items-center justify-between border-b px-4 py-3 text-sm font-semibold">Aktivitas terbaru <a class="link text-xs font-normal" href="{{ route('activity.index') }}">Lihat semua</a></h2>
            <ul class="divide-y text-sm">
                @foreach ($activity as $a)
                    <li class="flex flex-col gap-0.5 px-4 py-2 sm:flex-row sm:justify-between"><span><span class="font-medium">{{ $a->user_name ?? 'Sistem' }}</span> · {{ $a->description }}</span><span class="whitespace-nowrap text-xs text-slate-500">{{ Format::dateTime($a->occurred_at) }}</span></li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>
</x-app>
