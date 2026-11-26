@php use App\Support\Format; use App\Models\Personnel; use App\Models\PersonnelDocument; $active = $p->documents->whereNull('replaced_by_id'); $history = $p->documents->whereNotNull('replaced_by_id'); @endphp
<x-app :title="$p->number">
    <x-page-header :title="$p->number.' – '.$p->name" :subtitle="Personnel::TYPES[$p->type].($p->position ? ' · '.$p->position : '')">
        @can('personel.kelola')<a href="{{ route('personnel.edit', $p) }}" class="btn-secondary">Ubah profil</a>@endcan
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body text-sm">
            <dl class="space-y-2">
                <div><dt class="text-slate-500">Status</dt><dd>{{ ucfirst($p->status) }}{{ $p->employment ? ' · '.Personnel::EMPLOYMENT[$p->employment] : '' }} @if ($p->is_trainer)<x-badge color="blue">Trainer</x-badge>@endif</dd></div>
                <div><dt class="text-slate-500">Kontak</dt><dd>{{ $p->phone ?? '–' }}<br>{{ $p->email }}</dd></div>
                <div><dt class="text-slate-500">Bergabung</dt><dd>{{ Format::date($p->joined_at) }}</dd></div>
                <div><dt class="text-slate-500">Kategori bukti</dt><dd>{{ $p->evidenceCategoryId() }}. {{ $p->type === 'tenaga_ahli' ? 'Berkas Tenaga Ahli' : 'Berkas Tenaga Teknis' }}</dd></div>
                @if ($p->notes)<div><dt class="text-slate-500">Catatan</dt><dd class="whitespace-pre-line">{{ $p->notes }}</dd></div>@endif
            </dl>
        </section>
        <section class="card overflow-x-auto lg:col-span-2">
            <h2 class="border-b px-4 py-3 font-semibold">Berkas aktif</h2>
            <table class="table">
                <thead><tr><th>Berkas</th><th>Nomor / penerbit</th><th>Berlaku</th><th>Verifikasi</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($active as $d)
                    @php $st = $d->validity(); @endphp
                    <tr>
                        <td><span class="font-medium">{{ $d->title }}</span><div class="text-xs text-slate-500">{{ PersonnelDocument::CATEGORIES[$d->category] }}</div></td>
                        <td class="text-xs">{{ $d->original_number ?? '–' }}<br>{{ $d->issuer }}</td>
                        <td class="whitespace-nowrap text-xs">{{ $d->expires_at ? Format::date($d->expires_at) : 'Tanpa batas' }}<br><x-badge :color="$st->badge()">{{ $st->label() }}</x-badge></td>
                        <td class="text-xs">@if ($d->verified_at)✔ {{ $d->verifier?->name }}@else @can('personel.kelola')<form method="post" action="{{ route('personnel.documents.verify', [$p, $d]) }}">@csrf<button class="btn-secondary btn-sm">Verifikasi</button></form>@else Belum @endcan @endif</td>
                        <td class="whitespace-nowrap text-xs"><a class="link" href="{{ route('personnel.documents.download', [$p, $d]) }}">Unduh</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada berkas.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($history->isNotEmpty())
                <details class="border-t px-4 py-3 text-sm"><summary class="cursor-pointer text-slate-500">Riwayat berkas lama ({{ $history->count() }})</summary>
                    <ul class="mt-2 space-y-1">@foreach ($history as $d)<li>{{ $d->title }} – {{ $d->expires_at ? 's.d. '.Format::date($d->expires_at) : '' }} · <a class="link" href="{{ route('personnel.documents.download', [$p, $d]) }}">unduh</a></li>@endforeach</ul></details>
            @endif
        </section>
    </div>
    @can('personel.kelola')
    <form method="post" action="{{ route('personnel.documents.store', $p) }}" enctype="multipart/form-data" class="card card-body mt-5 max-w-4xl space-y-3">@csrf
        <h2 class="font-semibold">Unggah berkas</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="label">Kategori *</label><select name="category" class="input">@foreach (PersonnelDocument::CATEGORIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="label">Judul *</label><input name="title" required class="input" placeholder="Mis. SKP Ahli K3 Umum / Ijazah S1 Teknik"></div>
            <div><label class="label">Nomor asli</label><input name="original_number" class="input"></div>
            <div><label class="label">Penerbit</label><input name="issuer" class="input"></div>
            <div><label class="label">Memperbarui berkas lama</label><select name="replaces" class="input"><option value="">– Berkas baru –</option>@foreach ($active as $d)<option value="{{ $d->id }}">{{ $d->title }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal terbit</label><input type="date" name="issued_at" class="input"></div>
            <div><label class="label">Berlaku s.d.</label><input type="date" name="expires_at" class="input"><p class="help">Kosongkan bila tidak ada masa berlaku.</p></div>
            <div><label class="label">Berkas *</label><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.docx" class="block w-full text-sm"><p class="help">Maks. {{ $maxMb }} MB.</p></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="verified" value="1" class="rounded border-slate-300"> Sudah dicocokkan dengan dokumen asli (verifikasi)</label>
        <button class="btn-primary w-fit">Unggah</button>
    </form>
    @endcan
</x-app>
