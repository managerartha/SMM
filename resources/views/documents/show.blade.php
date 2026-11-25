@php use App\Support\Format; use App\Models\DocumentRevision; $u = auth()->user(); $cur = $document->currentRevision; @endphp
<x-app :title="$document->number">
    <x-page-header :title="$document->number.' – '.$document->title" :subtitle="$document->type->name.' · '.$document->division->name">
        @can('manage', $document)
            @if ($cur && ! $open)<a href="{{ route('documents.revisions.create', $document) }}" class="btn-primary">Buat revisi</a>@endif
        @endcan
        @can('archive', $document)
            @if ($document->isArchived())
                <form method="post" action="{{ route('documents.restore', $document) }}">@csrf<button class="btn-secondary">Pulihkan dari arsip</button></form>
            @elseif (! $open)
                <div x-data="{ o: false }">
                    <button type="button" class="btn-secondary" @click="o = true">Arsipkan</button>
                    <div x-cloak x-show="o" class="fixed inset-0 z-40 grid place-items-center bg-black/40 p-4">
                        <form method="post" action="{{ route('documents.archive', $document) }}" class="card card-body w-full max-w-md space-y-3" @click.outside="o = false">
                            @csrf
                            <h3 class="font-semibold">Arsipkan dokumen?</h3>
                            <p class="text-sm text-slate-600">Dokumen tidak dihapus; hanya disembunyikan dari daftar aktif. Nomor tidak akan dipakai ulang.</p>
                            <textarea name="note" required class="input" rows="2" placeholder="Alasan pengarsipan"></textarea>
                            <div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="o = false">Batal</button><button class="btn-danger">Arsipkan</button></div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </x-page-header>

    @if ($document->isArchived())
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">Dokumen ini diarsip pada {{ Format::date($document->archived_at) }}.</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Revisi berlaku --}}
        <section class="card card-body lg:col-span-2">
            <h2 class="mb-3 flex items-center gap-2 font-semibold">Revisi berlaku @if ($cur)<x-badge color="green">{{ $cur->label() }}</x-badge>@endif</h2>
            @if ($cur)
                <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Tanggal berlaku</dt><dd>{{ Format::date($cur->effective_date) }}</dd></div>
                    <div><dt class="text-slate-500">Disahkan oleh</dt><dd>{{ $cur->approved_by_name }}{{ $cur->approved_by_position ? ' ('.$cur->approved_by_position.')' : '' }}<span class="block text-xs text-slate-500">{{ Format::dateTime($cur->approved_at) }}</span></dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Ringkasan perubahan</dt><dd>{{ $cur->change_summary ?? '–' }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    @can('downloadRevision', $cur)<a href="{{ route('documents.download', [$document, $cur, 'pdf']) }}" class="btn-primary">Unduh salinan terkendali (PDF)</a>@endcan
                    @if ($cur->source_file_id)@can('downloadSource', $cur)<a href="{{ route('documents.download', [$document, $cur, 'sumber']) }}" class="btn-secondary">Unduh file sumber</a>@endcan @endif
                </div>
            @else
                <p class="text-sm text-slate-500">Belum ada revisi yang disahkan.</p>
            @endif
        </section>

        {{-- Info --}}
        <section class="card card-body">
            <h2 class="mb-3 font-semibold">Informasi</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Pemilik</dt><dd>{{ $document->owner->name }}</dd></div>
                <div><dt class="text-slate-500">Disahkan oleh (matriks)</dt><dd>{{ $approverRole }}</dd></div>
                <div><dt class="text-slate-500">Review</dt><dd>{{ $document->type->review_required ? 'Wajib' : 'Tidak wajib' }}</dd></div>
                <div><dt class="text-slate-500">Tinjauan berikutnya</dt><dd class="{{ $document->next_review_date?->isPast() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($document->next_review_date) }}</dd></div>
                <div><dt class="text-slate-500">Kategori bukti</dt><dd>{{ $document->evidenceCategory?->label() ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Cakupan akses</dt><dd>
                    @if ($document->access_scope === 'umum') Umum (semua pengguna)
                    @else Terbatas: divisi {{ $document->division->code }}@foreach ($document->accessRules as $a), {{ $a->division ? $a->division->code : (config('smm.roles')[$a->role_name] ?? $a->role_name) }}@endforeach
                    @endif
                </dd></div>
                @if ($document->summary)<div><dt class="text-slate-500">Ringkasan</dt><dd class="whitespace-pre-line">{{ $document->summary }}</dd></div>@endif
            </dl>
        </section>
    </div>

    {{-- Revisi yang sedang dikerjakan --}}
    @if ($open && ($u->can('manage', $document) || $u->can('downloadRevision', $open)))
        <section class="card card-body mt-5 ring-2 ring-brand-100">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h2 class="font-semibold">Sedang dikerjakan: {{ $open->label() }}</h2>
                <x-rev-status :status="$open->status" />
                @if ($open->reviewer)<span class="text-xs text-slate-500">Pereviu: {{ $open->reviewer->name }}</span>@endif
            </div>
            <dl class="mb-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Dibuat oleh</dt><dd>{{ $open->creator->name }} · {{ Format::dateTime($open->created_at) }}</dd></div>
                <div><dt class="text-slate-500">Alasan perubahan</dt><dd>{{ $open->change_reason ?? '–' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Ringkasan perubahan</dt><dd>{{ $open->change_summary ?? '–' }}</dd></div>
            </dl>
            <div class="mb-4 flex flex-wrap gap-2">
                @if ($open->pdf_file_id)@can('downloadRevision', $open)<a href="{{ route('documents.download', [$document, $open, 'pdf']) }}" class="btn-secondary btn-sm">Unduh PDF draf</a>@endcan @endif
                @if ($open->source_file_id)@can('downloadSource', $open)<a href="{{ route('documents.download', [$document, $open, 'sumber']) }}" class="btn-secondary btn-sm">Unduh file sumber</a>@endcan @endif
            </div>

            {{-- Aksi sesuai status --}}
            @if ($open->status === DocumentRevision::DRAFT && $u->can('manage', $document))
                <div class="grid gap-4 md:grid-cols-2">
                    <form method="post" action="{{ route('documents.submit', [$document, $open]) }}" class="space-y-2 rounded-md bg-slate-50 p-3">
                        @csrf
                        @if ($needsReview)
                            <label class="label" for="reviewer_id">Ajukan ke pereviu</label>
                            <select id="reviewer_id" name="reviewer_id" required class="input">
                                <option value="">Pilih pereviu…</option>
                                @foreach ($reviewers as $r)<option value="{{ $r->id }}">{{ $r->name }} – {{ $r->position ?? $r->roleLabels() }}</option>@endforeach
                            </select>
                            <x-field-error name="reviewer_id" />
                            <button class="btn-primary">Ajukan untuk review</button>
                        @else
                            <p class="text-sm text-slate-600">Jenis dokumen ini tidak wajib review. Dokumen langsung diajukan ke pengesah ({{ $approverRole }}).</p>
                            <button class="btn-primary">Ajukan untuk pengesahan</button>
                        @endif
                    </form>
                    <div class="flex flex-col gap-2 rounded-md bg-slate-50 p-3">
                        <a href="{{ route('documents.edit', [$document, $open]) }}" class="btn-secondary">Ubah draf / ganti file</a>
                        <form method="post" action="{{ route('documents.cancel', [$document, $open]) }}" x-data="{ o: false }" class="space-y-2">
                            @csrf
                            <button type="button" class="btn-secondary w-full text-red-700" @click="o = !o">Batalkan draf…</button>
                            <div x-cloak x-show="o" class="space-y-2">
                                <textarea name="note" rows="2" required class="input" placeholder="Alasan pembatalan"></textarea>
                                <button class="btn-danger btn-sm">Ya, batalkan draf</button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif ($open->status === DocumentRevision::REVIEW)
                @can('review', $open)
                    <form method="post" action="{{ route('documents.review', [$document, $open]) }}" class="space-y-2 rounded-md bg-blue-50 p-3" x-data="{ d: 'teruskan' }">
                        @csrf
                        <p class="text-sm font-medium">Keputusan review (paraf)</p>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="teruskan" x-model="d"> Teruskan ke pengesahan</label>
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="tolak" x-model="d"> Tolak</label>
                        </div>
                        <textarea name="note" rows="2" class="input" :required="d === 'tolak'" :placeholder="d === 'tolak' ? 'Catatan wajib: apa yang harus diperbaiki' : 'Catatan (opsional)'"></textarea>
                        <x-field-error name="note" />
                        <button class="btn-primary">Kirim keputusan</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">Menunggu review oleh {{ $open->reviewer?->name }}.</p>
                @endcan
            @elseif ($open->status === DocumentRevision::APPROVAL)
                @can('approve', $open)
                    <form method="post" action="{{ route('documents.approve', [$document, $open]) }}" class="space-y-2 rounded-md bg-violet-50 p-3" x-data="{ d: 'sahkan' }">
                        @csrf
                        <p class="text-sm font-medium">Pengesahan</p>
                        <p class="text-xs text-slate-600">Menekan "Sahkan" mencatat nama, jabatan, dan waktu Anda sebagai bukti pengesahan.@if ($cur) Revisi {{ $cur->label() }} otomatis menjadi Kedaluwarsa.@endif</p>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="sahkan" x-model="d"> Sahkan</label>
                            <label class="flex items-center gap-2"><input type="radio" name="decision" value="tolak" x-model="d"> Tolak</label>
                        </div>
                        <textarea name="note" rows="2" class="input" :required="d === 'tolak'" :placeholder="d === 'tolak' ? 'Catatan wajib: alasan penolakan' : 'Catatan (opsional)'"></textarea>
                        <x-field-error name="note" />
                        <button class="btn-success" x-text="d === 'sahkan' ? 'Sahkan dokumen' : 'Tolak dokumen'">Sahkan dokumen</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">Menunggu pengesahan oleh {{ $approverRole }}.
                        @if ($open->created_by === $u->id && $u->hasRole(app(App\Domain\Approval\ApprovalMatrix::class)->roleFor($document->type->approvalSubjectKey()))) Anda tidak dapat mengesahkan dokumen buatan sendiri tanpa review orang lain. @endif</p>
                @endcan
            @endif
        </section>
    @endif

    {{-- Riwayat revisi --}}
    <section class="card mt-5">
        <h2 class="border-b px-4 py-3 font-semibold">Riwayat revisi</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Revisi</th><th>Status</th><th>Tanggal</th><th>Ringkasan perubahan</th><th class="hidden md:table-cell">Pembuat</th><th class="hidden md:table-cell">Pengesah</th><th>Berkas</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($revisions as $r)
                    <tr>
                        <td class="whitespace-nowrap font-medium">{{ $r->label() }}</td>
                        <td><x-rev-status :status="$r->status" /></td>
                        <td class="whitespace-nowrap">{{ Format::date($r->effective_date ?? $r->created_at) }}@if ($r->expired_at)<div class="text-xs text-slate-500">s.d. {{ Format::date($r->expired_at) }}</div>@endif</td>
                        <td>{{ $r->change_summary }}@if ($r->change_reason && $r->revision_no > 0)<div class="text-xs text-slate-500">Alasan: {{ $r->change_reason }}</div>@endif
                            <details class="mt-1 text-xs"><summary class="cursor-pointer text-slate-500">Jejak alur ({{ $r->events->count() }})</summary>
                                <ul class="mt-1 space-y-0.5">@foreach ($r->events as $e)<li><span class="text-slate-500">{{ Format::dateTime($e->created_at) }}</span> · {{ $e->label() }} – {{ $e->actor_name }}{{ $e->actor_position ? ' ('.$e->actor_position.')' : '' }}{{ $e->note ? ': '.$e->note : '' }}</li>@endforeach</ul>
                            </details>
                        </td>
                        <td class="hidden md:table-cell">{{ $r->creator->name }}</td>
                        <td class="hidden md:table-cell">{{ $r->approved_by_name ?? '–' }}</td>
                        <td class="whitespace-nowrap">
                            @if ($r->pdf_file_id)@can('downloadRevision', $r)<a class="link" href="{{ route('documents.download', [$document, $r, 'pdf']) }}">PDF</a>@endcan @endif
                            @if ($r->source_file_id)@can('downloadSource', $r) · <a class="link" href="{{ route('documents.download', [$document, $r, 'sumber']) }}">Sumber</a>@endcan @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-xs text-slate-500">Revisi kedaluwarsa hanya bisa diunduh GM, MR, Direktur, dan Auditor dengan tanda air "TIDAK TERKENDALI".</p>
    </section>
</x-app>
