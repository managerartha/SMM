@php use App\Support\Format; use App\Models\Ncr; $u = auth()->user(); $cycle = $ncr->currentCycle();
    $curActions = $ncr->actions->where('cycle', $cycle); $oldActions = $ncr->actions->where('cycle', '<', $cycle);
    $curChecks = $ncr->checks->where('cycle', $cycle); $oldChecks = $ncr->checks->where('cycle', '<', $cycle);
    $canManage = $u->can('manage', $ncr); $canVerify = $u->can('verify', $ncr);
    $steps = [Ncr::OPEN => 1, Ncr::ACTION => 2, Ncr::MONITORING => 4, Ncr::CLOSING => 5, Ncr::CLOSED => 6, Ncr::CANCELLED => 0];
    $step = $steps[$ncr->status] ?? 0; @endphp
<x-app :title="$ncr->number">
    <x-page-header :title="$ncr->number.' – '.$ncr->title" :subtitle="(Ncr::SOURCES[$ncr->source] ?? $ncr->source).' · '.$ncr->division->name.' · '.ucfirst($ncr->severity)">
        <a href="{{ route('ncrs.print', $ncr) }}" target="_blank" class="btn-secondary">Cetak / PDF</a>
    </x-page-header>

    {{-- Langkah --}}
    <ol class="mb-5 grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
        @foreach (['Koreksi & akar masalah', 'Tindakan korektif', 'Verifikasi', 'Pemantauan 30/60/90', 'Penutupan'] as $i => $label)
            @php $n = $i + 1; $done = $step > $n || $step === 6; $now = $step === $n || ($n === 3 && $step === 2 && $curActions->isNotEmpty() && $curActions->where('status', 'rencana')->isEmpty()); @endphp
            <li class="rounded-md px-3 py-2 ring-1 {{ $done ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($now ? 'bg-brand-100 font-semibold text-brand-800 ring-brand-600' : 'bg-white text-slate-500 ring-slate-200') }}">{{ $n }}. {{ $label }}</li>
        @endforeach
    </ol>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <x-badge :color="$ncr->statusColor()">{{ $ncr->statusLabel() }}</x-badge>
                @if ($ncr->isOverdue())<x-badge color="red">Lewat target</x-badge>@endif
                @if ($ncr->reopened_count)<x-badge color="amber">Siklus ke-{{ $cycle }} (dibuka ulang {{ $ncr->reopened_count }}×)</x-badge>@endif
            </div>
            <p class="whitespace-pre-line text-sm">{{ $ncr->description }}</p>
            @if ($ncr->requirement)<p class="mt-2 text-sm"><span class="text-slate-500">Persyaratan:</span> {{ $ncr->requirement }}</p>@endif
            @include('partials.attachments', ['files' => $ncr->attachments])
            @if ($ncr->status === Ncr::CANCELLED)<p class="mt-3 rounded bg-slate-100 p-3 text-sm">Dibatalkan: {{ $ncr->cancel_reason }}</p>@endif
        </section>
        <section class="card card-body">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Ditemukan</dt><dd>{{ Format::date($ncr->found_at) }}</dd></div>
                @if ($ncr->complaint)<div><dt class="text-slate-500">Dari keluhan</dt><dd><a class="link" href="{{ route('complaints.show', $ncr->complaint) }}">{{ $ncr->complaint->number }}</a></dd></div>@endif
                <div><dt class="text-slate-500">Penanggung jawab</dt><dd>{{ $ncr->pic->name }}</dd></div>
                <div><dt class="text-slate-500">Target</dt><dd class="{{ $ncr->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($ncr->target_date) }}</dd></div>
                <div><dt class="text-slate-500">Dibuat oleh</dt><dd>{{ $ncr->creator->name }}, {{ Format::date($ncr->created_at) }}</dd></div>
                <div><dt class="text-slate-500">Ditutup oleh (matriks)</dt><dd>{{ $closerRole }}</dd></div>
            </dl>
            @if ($canManage && $ncr->status === Ncr::OPEN)
            <details class="mt-3 text-sm"><summary class="cursor-pointer link">Ubah data NCR</summary>
                <form method="post" action="{{ route('ncrs.update', $ncr) }}" class="mt-2 space-y-2">@csrf @method('PUT')
                    <input name="title" value="{{ $ncr->title }}" required class="input">
                    <textarea name="description" rows="3" required class="input">{{ $ncr->description }}</textarea>
                    <input name="requirement" value="{{ $ncr->requirement }}" class="input" placeholder="Persyaratan">
                    <select name="division_id" class="input">@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected($ncr->division_id === $d->id)>{{ $d->code }}</option>@endforeach</select>
                    <select name="severity" class="input"><option value="minor">Minor</option><option value="mayor" @selected($ncr->severity === 'mayor')>Mayor</option></select>
                    <select name="pic_id" class="input">@foreach ($users as $x)<option value="{{ $x->id }}" @selected($ncr->pic_id === $x->id)>{{ $x->name }}</option>@endforeach</select>
                    <input type="date" name="target_date" value="{{ $ncr->target_date?->toDateString() }}" class="input">
                    <button class="btn-secondary btn-sm">Simpan</button>
                </form>
            </details>
            @endif
        </section>
    </div>

    {{-- 1. Koreksi & akar masalah --}}
    <section class="card card-body mt-5">
        <h2 class="mb-3 font-semibold">1. Koreksi & analisis akar masalah</h2>
        @if ($canManage && $ncr->status === Ncr::OPEN)
            <form method="post" action="{{ route('ncrs.analysis', $ncr) }}" class="grid gap-3 md:grid-cols-2">@csrf
                <div><label class="label">Koreksi (tindakan segera menangani dampak)</label><textarea name="correction" rows="4" class="input">{{ old('correction', $ncr->correction) }}</textarea></div>
                <div class="space-y-2"><label class="label">Analisis akar masalah</label>
                    <select name="root_cause_method" class="input">@foreach (Ncr::METHODS as $k => $v)<option value="{{ $k }}" @selected($ncr->root_cause_method === $k)>{{ $v }}</option>@endforeach</select>
                    <textarea name="root_cause" rows="3" class="input" placeholder="Mengapa terjadi? (mis. 5 Why: …)">{{ old('root_cause', $ncr->root_cause) }}</textarea></div>
                <div class="md:col-span-2"><button class="btn-secondary">Simpan</button></div>
            </form>
        @else
            <dl class="grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="text-slate-500">Koreksi</dt><dd class="whitespace-pre-line">{{ $ncr->correction ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Akar masalah ({{ Ncr::METHODS[$ncr->root_cause_method] ?? '–' }})</dt><dd class="whitespace-pre-line">{{ $ncr->root_cause ?? '–' }}</dd></div>
            </dl>
        @endif
    </section>

    {{-- 2. Tindakan korektif --}}
    <section class="card card-body mt-5">
        <h2 class="mb-3 font-semibold">2. Tindakan korektif @if ($ncr->reopened_count)<span class="text-sm font-normal text-slate-500">(siklus ke-{{ $cycle }})</span>@endif</h2>
        <div class="space-y-3">
            @forelse ($curActions as $a)
                <div class="rounded-md p-3 ring-1 {{ $a->status === 'selesai' ? 'bg-emerald-50 ring-emerald-200' : ($a->isOverdue() ? 'bg-red-50 ring-red-200' : 'ring-slate-200') }}">
                    <div class="flex flex-wrap items-start justify-between gap-2 text-sm">
                        <p class="font-medium">{{ $a->description }}</p>
                        <span class="text-xs text-slate-600">PIC {{ $a->pic->name }} · tenggat {{ Format::date($a->due_date) }} · <b>{{ $a->status === 'selesai' ? 'Selesai '.Format::date($a->completed_at) : ($a->isOverdue() ? 'Lewat tenggat' : 'Rencana') }}</b></span>
                    </div>
                    @if ($a->completion_note)<p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $a->completion_note }}</p>@endif
                    @include('partials.attachments', ['files' => $a->attachments])
                    @can('completeAction', $a)
                        <form method="post" action="{{ route('ncrs.actions.complete', [$ncr, $a]) }}" enctype="multipart/form-data" class="mt-2 space-y-2" x-data="{ o: false }">@csrf
                            <button type="button" class="btn-success btn-sm" x-show="!o" @click="o = true">Tandai selesai…</button>
                            <div x-cloak x-show="o" class="space-y-2">
                                <textarea name="completion_note" rows="2" required class="input" placeholder="Apa yang sudah dilakukan"></textarea>
                                <input type="file" name="evidence[]" multiple class="block w-full text-sm">
                                <button class="btn-success btn-sm">Simpan sebagai selesai</button>
                            </div>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada tindakan korektif.</p>
            @endforelse
        </div>
        @if ($canManage && in_array($ncr->status, [Ncr::OPEN, Ncr::ACTION]))
            <form method="post" action="{{ route('ncrs.actions.store', $ncr) }}" class="mt-4 grid gap-2 rounded-md bg-slate-50 p-3 md:grid-cols-6">@csrf
                <textarea name="action_description" rows="2" required class="input md:col-span-3" placeholder="Tindakan korektif untuk menghilangkan akar masalah"></textarea>
                <select name="action_pic_id" required class="input md:col-span-1"><option value="">PIC…</option>@foreach ($users as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select>
                <input type="date" name="action_due_date" required value="{{ $ncr->target_date?->toDateString() }}" class="input md:col-span-1">
                <button class="btn-secondary md:col-span-1">+ Tambah</button>
            </form>
        @endif
        @if ($canManage && $ncr->status === Ncr::OPEN)
            <form method="post" action="{{ route('ncrs.start', $ncr) }}" class="mt-3">@csrf
                <button class="btn-primary">Mulai tindakan korektif</button>
                <span class="help ml-2">Syarat: koreksi, akar masalah, dan minimal satu tindakan sudah diisi. PIC tindakan akan diberi tahu.</span>
            </form>
        @endif
        @if ($oldActions->isNotEmpty())
            <details class="mt-4 text-sm"><summary class="cursor-pointer text-slate-500">Tindakan siklus sebelumnya ({{ $oldActions->count() }})</summary>
                <ul class="mt-2 space-y-1">@foreach ($oldActions as $a)<li>Siklus {{ $a->cycle }}: {{ $a->description }} – {{ $a->pic->name }} ({{ $a->status }})</li>@endforeach</ul></details>
        @endif
    </section>

    {{-- 3. Verifikasi --}}
    <section class="card card-body mt-5">
        <h2 class="mb-3 font-semibold">3. Verifikasi pelaksanaan</h2>
        @if ($ncr->verified_at)
            <p class="text-sm">{{ $ncr->verification_note }} <span class="text-xs text-slate-500">– {{ $ncr->verifier?->name }}, {{ Format::dateTime($ncr->verified_at) }}</span></p>
        @elseif ($ncr->status === Ncr::ACTION && $curActions->isNotEmpty() && $curActions->where('status', 'rencana')->isEmpty())
            @if ($canVerify)
                <form method="post" action="{{ route('ncrs.verify', $ncr) }}" class="space-y-2">@csrf
                    <textarea name="verification_note" rows="2" required class="input" placeholder="Hasil pemeriksaan: semua tindakan sudah dilaksanakan sesuai rencana, bukti …"></textarea>
                    <button class="btn-primary">Verifikasi & mulai pemantauan 30/60/90 hari</button>
                </form>
            @else
                <p class="text-sm text-slate-600">Semua tindakan selesai. Menunggu verifikasi oleh MR/GM (bukan penanggung jawab NCR).</p>
            @endif
        @else
            <p class="text-sm text-slate-500">Dilakukan setelah semua tindakan korektif selesai.</p>
        @endif
    </section>

    {{-- 4. Pemantauan efektivitas --}}
    <section class="card card-body mt-5">
        <h2 class="mb-3 font-semibold">4. Pemantauan efektivitas</h2>
        @if ($curChecks->isEmpty())<p class="text-sm text-slate-500">Dijadwalkan otomatis 30, 60, dan 90 hari setelah verifikasi.</p>@endif
        <div class="grid gap-3 md:grid-cols-3">
            @foreach ($curChecks as $ch)
                <div class="rounded-md p-3 text-sm ring-1 {{ $ch->result === 'efektif' ? 'bg-emerald-50 ring-emerald-200' : ($ch->result ? 'bg-red-50 ring-red-200' : 'ring-slate-200') }}">
                    <p class="font-semibold">Hari ke-{{ $ch->day }}</p>
                    <p class="text-xs text-slate-500">Jadwal {{ Format::date($ch->due_date) }}</p>
                    @if ($ch->result)
                        <p class="mt-1 font-medium">{{ $ch->result === 'efektif' ? '✔ Efektif' : '✘ Tidak efektif' }}</p>
                        <p class="whitespace-pre-line">{{ $ch->note }}</p>
                        <p class="text-xs text-slate-500">{{ $ch->checker?->name }}, {{ Format::date($ch->checked_at) }}</p>
                        @include('partials.attachments', ['files' => $ch->attachments])
                    @elseif ($canVerify && $ncr->status === Ncr::MONITORING && $ch->isOpenForInput() && $curChecks->where('day', '<', $ch->day)->whereNull('result')->isEmpty())
                        <form method="post" action="{{ route('ncrs.effectiveness', [$ncr, $ch]) }}" enctype="multipart/form-data" class="mt-2 space-y-2">@csrf
                            <select name="result" class="input"><option value="efektif">Efektif (masalah tidak berulang)</option><option value="tidak_efektif">Tidak efektif (masalah berulang)</option></select>
                            <textarea name="note" rows="2" required class="input" placeholder="Data/bukti yang diperiksa"></textarea>
                            <input type="file" name="evidence[]" multiple class="block w-full text-xs">
                            <button class="btn-primary btn-sm">Simpan hasil</button>
                        </form>
                    @else
                        <p class="mt-1 text-xs text-slate-500">Belum waktunya / menunggu.</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($oldChecks->isNotEmpty())
            <details class="mt-4 text-sm"><summary class="cursor-pointer text-slate-500">Pemantauan siklus sebelumnya</summary>
                <ul class="mt-2 space-y-1">@foreach ($oldChecks as $ch)<li>Siklus {{ $ch->cycle }} hari ke-{{ $ch->day }}: {{ $ch->result ?? '–' }} {{ $ch->note ? '– '.$ch->note : '' }}</li>@endforeach</ul></details>
        @endif
    </section>

    {{-- 5. Penutupan --}}
    <section class="card card-body mt-5">
        <h2 class="mb-3 font-semibold">5. Penutupan</h2>
        @if ($ncr->status === Ncr::CLOSED)
            <p class="text-sm">Ditutup oleh <b>{{ $ncr->closed_by_name }}</b>{{ $ncr->closed_by_position ? ' ('.$ncr->closed_by_position.')' : '' }} pada {{ Format::dateTime($ncr->closed_at) }}. {{ $ncr->closure_note }}</p>
        @elseif ($ncr->status === Ncr::CLOSING)
            @can('close', $ncr)
                <form method="post" action="{{ route('ncrs.close', $ncr) }}" class="space-y-2" x-data="{ d: 'tutup' }">@csrf
                    <div class="flex gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="decision" value="tutup" x-model="d"> Tutup NCR</label><label class="flex items-center gap-2"><input type="radio" name="decision" value="tolak" x-model="d"> Tolak (buka ulang)</label></div>
                    <textarea name="note" rows="2" class="input" :required="d === 'tolak'" :placeholder="d === 'tolak' ? 'Alasan penolakan (wajib)' : 'Catatan penutupan (opsional)'"></textarea>
                    <button class="btn-success">Kirim keputusan</button>
                </form>
            @else
                <p class="text-sm text-slate-600">Menunggu penutupan oleh {{ $closerRole }}.</p>
            @endcan
        @else
            <p class="text-sm text-slate-500">Setelah ketiga pemantauan efektivitas dinilai efektif.</p>
        @endif
    </section>

    <section class="card card-body mt-5">
        <h2 class="mb-2 font-semibold">Riwayat</h2>
        <ul class="space-y-1 text-sm">
            @foreach ($ncr->events as $e)
                <li><span class="text-xs text-slate-500">{{ Format::dateTime($e->created_at) }}</span> · <b>{{ $e->label() }}</b> – {{ $e->actor_name }}{{ $e->note ? ': '.\Illuminate\Support\Str::limit($e->note, 200) : '' }}</li>
            @endforeach
        </ul>
        @can('cancel', $ncr)
            <form method="post" action="{{ route('ncrs.cancel', $ncr) }}" class="mt-4 space-y-2" x-data="{ o: false }">@csrf
                <button type="button" class="btn-secondary btn-sm text-red-700" x-show="!o" @click="o = true">Batalkan NCR…</button>
                <div x-cloak x-show="o" class="space-y-2"><textarea name="note" rows="2" required class="input" placeholder="Alasan (mis. bukan ketidaksesuaian, duplikat)"></textarea><button class="btn-danger btn-sm">Batalkan</button></div>
            </form>
        @endcan
    </section>
</x-app>
