@php use App\Support\Format; use App\Models\Risk; use App\Domain\Risks\RiskLevel; $lv = $risk->levelEnum(); @endphp
<x-app :title="$risk->number">
    <x-page-header :title="$risk->number.' – '.$risk->title" :subtitle="Risk::TYPES[$risk->type].' · '.$risk->division->name.($risk->process ? ' · '.$risk->process : '')">
        @can('manage', $risk)<a href="{{ route('risks.edit', $risk) }}" class="btn-secondary">Ubah</a>@endcan
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body lg:col-span-2 space-y-3 text-sm">
            <div class="flex flex-wrap items-center gap-2"><x-badge :color="$lv->color()">Skor {{ $risk->score }} · {{ $lv->label() }}</x-badge><span class="text-slate-500">Kemungkinan {{ $risk->likelihood }} × Dampak {{ $risk->impact }}</span>@if ($risk->status === 'ditutup')<x-badge>Ditutup</x-badge>@endif</div>
            @foreach (['description' => 'Uraian', 'cause' => 'Penyebab', 'consequence' => 'Dampak / manfaat', 'existing_controls' => 'Pengendalian yang ada', 'treatment' => 'Rencana penanganan'] as $f => $label)
                @if ($risk->$f)<div><p class="text-slate-500">{{ $label }}</p><p class="whitespace-pre-line">{{ $risk->$f }}</p></div>@endif
            @endforeach
            @if ($risk->close_note)<p class="rounded bg-slate-100 p-2">Ditutup: {{ $risk->close_note }}</p>@endif
        </section>
        <section class="card card-body">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Pemilik</dt><dd>{{ $risk->owner->name }}</dd></div>
                <div><dt class="text-slate-500">PIC penanganan</dt><dd>{{ $risk->treatmentPic?->name ?? '–' }} {{ $risk->treatment_due_date ? '· '.Format::date($risk->treatment_due_date) : '' }}</dd></div>
                <div><dt class="text-slate-500">Status penanganan</dt><dd>{{ Risk::TREATMENT_STATUSES[$risk->treatment_status] ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Tinjauan berikutnya</dt><dd class="{{ $risk->isReviewDue() ? 'font-semibold text-amber-700' : '' }}">{{ Format::date($risk->review_date) }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <section class="card">
            <h2 class="border-b px-4 py-3 font-semibold">Riwayat penilaian</h2>
            <ul class="divide-y text-sm">
                @foreach ($risk->assessments as $a)
                    <li class="flex items-start justify-between gap-2 px-4 py-2.5"><span>{{ $a->note }}<span class="block text-xs text-slate-500">{{ $a->assessor->name }}, {{ Format::dateTime($a->assessed_at) }}</span></span>
                        <x-badge :color="$a->levelEnum()->color()">K{{ $a->likelihood }}×D{{ $a->impact }} = {{ $a->score }}</x-badge></li>
                @endforeach
            </ul>
        </section>
        @can('manage', $risk)
        <section class="space-y-5">
            @if ($risk->status === 'aktif')
            <form method="post" action="{{ route('risks.reassess', $risk) }}" class="card card-body space-y-3" x-data="{ l: {{ $risk->likelihood }}, i: {{ $risk->impact }} }">@csrf
                <h2 class="font-semibold">Penilaian ulang</h2>
                <div class="grid grid-cols-3 items-end gap-2">
                    <div><label class="label">Kemungkinan</label><select name="likelihood" x-model.number="l" class="input">@for ($x = 1; $x <= 5; $x++)<option value="{{ $x }}">{{ $x }}</option>@endfor</select></div>
                    <div><label class="label">Dampak</label><select name="impact" x-model.number="i" class="input">@for ($x = 1; $x <= 5; $x++)<option value="{{ $x }}">{{ $x }}</option>@endfor</select></div>
                    <div class="pb-2 text-sm">Skor <b x-text="l * i"></b></div>
                </div>
                <textarea name="note" rows="2" required class="input" placeholder="Dasar penilaian ulang (mis. tindakan sudah selesai, kondisi berubah)"></textarea>
                <button class="btn-primary">Simpan penilaian</button>
            </form>
            <form method="post" action="{{ route('risks.close', $risk) }}" class="card card-body space-y-2" x-data="{ o: false }">@csrf
                <button type="button" class="btn-secondary w-fit" x-show="!o" @click="o = true">Tutup risiko/peluang…</button>
                <div x-cloak x-show="o" class="space-y-2"><textarea name="note" rows="2" required class="input" placeholder="Alasan (mis. proses tidak ada lagi)"></textarea><button class="btn-danger btn-sm">Tutup</button></div>
            </form>
            @else
            <form method="post" action="{{ route('risks.reopen', $risk) }}" class="card card-body">@csrf<button class="btn-secondary w-fit">Buka kembali</button></form>
            @endif
        </section>
        @endcan
    </div>
</x-app>
