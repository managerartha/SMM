@php use App\Support\Format; @endphp
<x-app title="Dashboard">
    <x-page-header title="Selamat datang, {{ auth()->user()->name }}" subtitle="{{ Format::date(now()) }} · {{ auth()->user()->roleLabels() }}" />

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card card-body"><p class="text-xs text-slate-500">Dokumen berlaku</p><p class="mt-1 text-2xl font-semibold">{{ $stats['berlaku'] }}</p></div>
        @if (! is_null($stats['proses']))<div class="card card-body"><p class="text-xs text-slate-500">Revisi dalam proses</p><p class="mt-1 text-2xl font-semibold">{{ $stats['proses'] }}</p></div>@endif
        @if (! is_null($stats['legalitas']))<div class="card card-body"><p class="text-xs text-slate-500">Legalitas & kontrak</p><p class="mt-1 text-2xl font-semibold">{{ $stats['legalitas'] }}</p></div>@endif
        <div class="card card-body"><p class="text-xs text-slate-500">Menunggu tindakan saya</p><p class="mt-1 text-2xl font-semibold {{ ($toReview->count() + $toApprove->count()) ? 'text-red-600' : '' }}">{{ $toReview->count() + $toApprove->count() }}</p></div>
    </div>

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
                @if ($toApprove->isEmpty() && $toReview->isEmpty() && $myDrafts->isEmpty())
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
