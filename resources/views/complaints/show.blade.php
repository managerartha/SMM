@php use App\Support\Format; use App\Models\Complaint; @endphp
<x-app :title="$c->number">
    <x-page-header :title="$c->number.' – '.$c->subject" :subtitle="Complaint::TYPES[$c->type].' · '.(Complaint::SOURCES[$c->source] ?? $c->source)">
        @can('update', $c)<a href="{{ route('complaints.edit', $c) }}" class="btn-secondary">Ubah</a>@endcan
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center gap-2"><x-badge :color="$c->statusColor()">{{ $c->statusLabel() }}</x-badge>
                @if ($c->isOverdue())<x-badge color="red">Lewat batas tanggapan</x-badge>@endif
                @if ($c->severity)<x-badge :color="['rendah' => 'green', 'sedang' => 'amber', 'tinggi' => 'red'][$c->severity]">Keparahan {{ $c->severity }}</x-badge>@endif</div>
            <p class="whitespace-pre-line text-sm">{{ $c->description }}</p>
            @include('partials.attachments', ['files' => $c->attachments->where('purpose', 'complaint_attachment')])
            @if ($c->response)
                <div class="mt-4 rounded-md bg-emerald-50 p-3 text-sm">
                    <p class="font-medium text-emerald-800">Tanggapan kepada pelapor ({{ Format::dateTime($c->responded_at) }}, {{ $c->closer?->name }})</p>
                    <p class="mt-1 whitespace-pre-line">{{ $c->response }}</p>
                    @if ($c->resolution)<p class="mt-2 font-medium text-emerald-800">Penyelesaian / tindak lanjut</p><p class="whitespace-pre-line">{{ $c->resolution }}</p>@endif
                </div>
            @elseif ($c->status === 'dibatalkan')
                <p class="mt-4 rounded bg-slate-100 p-3 text-sm">{{ $c->resolution }}</p>
            @endif
        </section>
        <section class="card card-body">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Diterima</dt><dd>{{ Format::date($c->received_at) }}{{ $c->channel ? ' via '.Complaint::CHANNELS[$c->channel] : '' }}</dd></div>
                <div><dt class="text-slate-500">Pelapor</dt><dd>{{ $c->reporter_name ?? '–' }}{{ $c->reporter_org ? ' ('.$c->reporter_org.')' : '' }}<div class="text-xs text-slate-500">{{ $c->reporter_contact }}</div></dd></div>
                @if ($c->class_code)<div><dt class="text-slate-500">Kode kelas LMS</dt><dd>{{ $c->class_code }}</dd></div>@endif
                <div><dt class="text-slate-500">Kategori / divisi</dt><dd>{{ Complaint::CATEGORIES[$c->category] ?? '–' }} · {{ $c->division?->code ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Penanggung jawab</dt><dd>{{ $c->pic?->name ?? 'Belum ditugaskan' }}</dd></div>
                @if ($c->response_due_date)<div><dt class="text-slate-500">Batas tanggapan</dt><dd class="{{ $c->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($c->response_due_date) }}</dd></div>@endif
                <div><dt class="text-slate-500">Dicatat oleh</dt><dd>{{ $c->creator?->name ?? 'Sistem (dari LMS)' }}, {{ Format::dateTime($c->created_at) }}</dd></div>
                @if ($c->ncr)<div><dt class="text-slate-500">NCR terkait</dt><dd><a class="link" href="{{ route('ncrs.show', $c->ncr) }}">{{ $c->ncr->number }}</a> <x-badge :color="$c->ncr->statusColor()">{{ $c->ncr->statusLabel() }}</x-badge></dd></div>@endif
            </dl>
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        @can('assign', $c)
        <form method="post" action="{{ route('complaints.assign', $c) }}" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Tugaskan penanggung jawab</h2>
            <select name="pic_id" required class="input"><option value="">Pilih…</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($c->pic_id === $u->id)>{{ $u->name }} – {{ $u->position }}</option>@endforeach</select>
            <div><label class="label">Batas tanggapan</label><input type="date" name="response_due_date" value="{{ $c->response_due_date?->toDateString() }}" class="input"></div>
            <button class="btn-secondary">Tugaskan</button>
        </form>
        @endcan
        @can('handle', $c)
        <form method="post" action="{{ route('complaints.respond', $c) }}" enctype="multipart/form-data" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Tanggapi & selesaikan</h2>
            <div><label class="label">Tanggapan kepada pelapor *</label><textarea name="response" rows="3" required class="input" placeholder="Apa yang disampaikan kepada pelapor">{{ old('response') }}</textarea><x-field-error name="response" /></div>
            <div><label class="label">Penyelesaian / tindak lanjut internal</label><textarea name="resolution" rows="2" class="input">{{ old('resolution') }}</textarea></div>
            <div><label class="label">Bukti (opsional)</label><input type="file" name="attachments[]" multiple class="block w-full text-sm"></div>
            <button class="btn-success">Tandai selesai</button>
        </form>
        @endcan
        @can('escalate', $c)
        <form method="post" action="{{ route('complaints.escalate', $c) }}" class="card card-body space-y-3" x-data="{ o: false }">@csrf
            <h2 class="font-semibold">Jadikan NCR</h2>
            <p class="text-sm text-slate-600">Bila keluhan menunjukkan ketidaksesuaian sistem/proses yang perlu tindakan korektif (analisis akar masalah).</p>
            <button type="button" class="btn-secondary" x-show="!o" @click="o = true">Buat NCR dari keluhan ini…</button>
            <div x-cloak x-show="o" class="space-y-3">
                <div><label class="label">Judul NCR</label><input name="title" value="{{ $c->subject }}" required class="input"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Divisi</label><select name="division_id" required class="input">@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected($c->division_id == $d->id)>{{ $d->code }}</option>@endforeach</select></div>
                    <div><label class="label">Tingkat</label><select name="severity" class="input"><option value="minor">Minor</option><option value="mayor">Mayor</option></select></div>
                    <div><label class="label">Penanggung jawab NCR</label><select name="pic_id" required class="input"><option value="">Pilih…</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                    <div><label class="label">Target selesai</label><input type="date" name="target_date" value="{{ now()->addDays(30)->toDateString() }}" required class="input"></div>
                </div>
                <button class="btn-primary">Buat NCR</button>
            </div>
        </form>
        @endcan
        @can('assign', $c)
        <form method="post" action="{{ route('complaints.cancel', $c) }}" class="card card-body space-y-2" x-data="{ o: false }">@csrf
            <button type="button" class="btn-secondary w-fit text-red-700" x-show="!o" @click="o = true">Batalkan catatan ini…</button>
            <div x-cloak x-show="o" class="space-y-2"><textarea name="note" rows="2" required class="input" placeholder="Alasan (mis. duplikat, salah input)"></textarea><button class="btn-danger btn-sm">Batalkan</button></div>
        </form>
        @endcan
    </div>
</x-app>
