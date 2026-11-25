@php use App\Support\Format; $v = $doc->currentVersion; $st = $v?->validity(); @endphp
<x-app :title="$doc->number">
    <x-page-header :title="$doc->number.' – '.$doc->title" :subtitle="$doc->category->name.' · '.$doc->issuer">
        @can('update', $doc)
            <a href="{{ route('external-documents.renew', $doc) }}" class="btn-primary">Perpanjang / versi baru</a>
            <a href="{{ route('external-documents.edit', $doc) }}" class="btn-secondary">Ubah info</a>
        @endcan
        @can('archive', $doc)
            @if ($doc->isArchived())
                <form method="post" action="{{ route('external-documents.restore', $doc) }}">@csrf<button class="btn-secondary">Pulihkan</button></form>
            @else
                <form method="post" action="{{ route('external-documents.archive', $doc) }}" x-data="{ o: false }" class="flex items-center gap-2">@csrf
                    <button type="button" class="btn-secondary" @click="o = !o" x-show="!o">Arsipkan</button>
                    <input x-cloak x-show="o" name="note" required class="input" placeholder="Alasan">
                    <button x-cloak x-show="o" class="btn-danger">Arsipkan</button>
                </form>
            @endif
        @endcan
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body lg:col-span-2">
            <h2 class="mb-3 flex items-center gap-2 font-semibold">Versi berlaku @if ($v) <x-badge>Versi {{ $v->version_no }}</x-badge> <x-badge :color="$st->badge()">{{ $st->label() }}</x-badge>@endif</h2>
            @if ($v)
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Nomor asli penerbit</dt><dd class="font-medium">{{ $v->original_number }}</dd></div>
                <div><dt class="text-slate-500">Tanggal terbit</dt><dd>{{ Format::date($v->issued_at) }}</dd></div>
                <div><dt class="text-slate-500">Tanggal berakhir</dt><dd>{{ $v->expires_at ? Format::date($v->expires_at).' ('.($v->daysLeft() >= 0 ? 'sisa '.$v->daysLeft().' hari' : 'lewat '.abs($v->daysLeft()).' hari').')' : 'Tidak ada masa berlaku' }}</dd></div>
                <div><dt class="text-slate-500">Verifikasi keaslian</dt><dd>
                    @if ($v->verified_at) <x-badge color="green">Diverifikasi</x-badge> {{ $v->verifier?->name }}, {{ Format::date($v->verified_at) }}
                    @else <x-badge color="amber">Belum diverifikasi</x-badge>
                        @can('verify', $doc)<form method="post" action="{{ route('external-documents.verify', [$doc, $v]) }}" class="mt-1" onsubmit="return confirm('Anda menyatakan telah memeriksa keaslian dokumen ini?')">@csrf<button class="btn-success btn-sm">Verifikasi sekarang</button></form>@endcan
                    @endif</dd></div>
            </dl>
            <div class="mt-4"><a href="{{ route('external-documents.download', [$doc, $v]) }}" class="btn-primary">Unduh berkas ({{ strtoupper($v->file->extension) }}, {{ $v->file->sizeLabel() }})</a></div>
            @endif
        </section>
        <section class="card card-body">
            <h2 class="mb-3 font-semibold">Informasi</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Kategori bukti</dt><dd>{{ $doc->evidenceCategory?->label() ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Dicatat oleh</dt><dd>{{ $doc->creator->name }}, {{ Format::date($doc->created_at) }}</dd></div>
                @if ($doc->notes)<div><dt class="text-slate-500">Catatan</dt><dd class="whitespace-pre-line">{{ $doc->notes }}</dd></div>@endif
                <div><dt class="text-slate-500">Pengingat email</dt><dd>{{ implode(', ', (array) App\Support\Settings::get('reminder.external_days')) }} hari sebelum berakhir</dd></div>
            </dl>
        </section>
    </div>

    <section class="card mt-5">
        <h2 class="border-b px-4 py-3 font-semibold">Riwayat versi</h2>
        <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Versi</th><th>Nomor asli</th><th>Terbit</th><th>Berakhir</th><th class="hidden md:table-cell">Verifikasi</th><th class="hidden md:table-cell">Diunggah</th><th>Berkas</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($doc->versions as $ver)
                <tr class="{{ $ver->id === $doc->current_version_id ? 'bg-emerald-50/50' : '' }}">
                    <td>{{ $ver->version_no }}{{ $ver->id === $doc->current_version_id ? ' (berlaku)' : '' }}</td>
                    <td>{{ $ver->original_number }}</td>
                    <td class="whitespace-nowrap">{{ Format::date($ver->issued_at) }}</td>
                    <td class="whitespace-nowrap">{{ $ver->expires_at ? Format::date($ver->expires_at) : '–' }}</td>
                    <td class="hidden md:table-cell">{{ $ver->verified_at ? $ver->verifier?->name.', '.Format::date($ver->verified_at) : 'Belum' }}</td>
                    <td class="hidden md:table-cell">{{ $ver->creator->name }}, {{ Format::date($ver->created_at) }}</td>
                    <td><a class="link" href="{{ route('external-documents.download', [$doc, $ver]) }}">Unduh</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </section>
</x-app>
