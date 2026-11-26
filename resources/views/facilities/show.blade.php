@php use App\Support\Format; use App\Models\Facility; use App\Models\FacilityLog; @endphp
<x-app :title="$f->number">
    <x-page-header :title="$f->number.' – '.$f->name" :subtitle="(config('smm.facility_categories')[$f->category] ?? $f->category).($f->location ? ' · '.$f->location : '')">
        @can('sarana.kelola')<a href="{{ route('facilities.edit', $f) }}" class="btn-secondary">Ubah</a>@endcan
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body text-sm">
            <dl class="space-y-2">
                <div><dt class="text-slate-500">Kondisi</dt><dd><x-badge :color="$f->conditionColor()">{{ Facility::CONDITIONS[$f->condition] }}</x-badge> · {{ $f->quantity }} unit · {{ $f->status }}</dd></div>
                <div><dt class="text-slate-500">Merek / seri</dt><dd>{{ $f->brand_model ?? '–' }} {{ $f->serial_number ? '· '.$f->serial_number : '' }}</dd></div>
                <div><dt class="text-slate-500">Diperoleh</dt><dd>{{ Format::date($f->acquired_at) }}</dd></div>
                <div><dt class="text-slate-500">Pemeriksaan berikutnya</dt><dd class="{{ $f->isInspectionDue() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($f->next_inspection_at) }} {{ $f->inspection_interval_months ? '(tiap '.$f->inspection_interval_months.' bln)' : '' }}</dd></div>
                @if ($f->needs_calibration)<div><dt class="text-slate-500">Kalibrasi berikutnya</dt><dd class="{{ $f->isCalibrationDue() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($f->next_calibration_at) }}</dd></div>@endif
                @if ($f->notes)<div><dt class="text-slate-500">Catatan</dt><dd class="whitespace-pre-line">{{ $f->notes }}</dd></div>@endif
            </dl>
            <p class="mt-3 font-medium">Foto / dokumen</p>
            @include('partials.attachments', ['files' => $f->attachments])
        </section>
        <section class="card lg:col-span-2">
            <h2 class="border-b px-4 py-3 font-semibold">Riwayat pemeriksaan, kalibrasi & perbaikan</h2>
            <ul class="divide-y text-sm">
                @forelse ($f->logs as $l)
                    <li class="px-4 py-2.5"><span class="font-medium">{{ FacilityLog::TYPES[$l->type] }}</span> · {{ Format::date($l->performed_at) }} · {{ $l->performer->name }}
                        @if ($l->condition_after)<x-badge>{{ Facility::CONDITIONS[$l->condition_after] }}</x-badge>@endif
                        <p class="whitespace-pre-line text-slate-700">{{ $l->result }}</p>
                        <p class="text-xs text-slate-500">{{ $l->supplier ? 'Oleh '.$l->supplier->name.' · ' : '' }}{{ $l->cost ? 'Biaya Rp '.number_format($l->cost, 0, ',', '.').' · ' : '' }}{{ $l->valid_until ? 'Berlaku s.d. '.Format::date($l->valid_until) : '' }}</p>
                        @include('partials.attachments', ['files' => $l->attachments])</li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Belum ada riwayat.</li>
                @endforelse
            </ul>
        </section>
    </div>
    @can('sarana.kelola')
    <form method="post" action="{{ route('facilities.logs.store', $f) }}" enctype="multipart/form-data" class="card card-body mt-5 max-w-4xl space-y-3" x-data="{ t: 'pemeriksaan' }">@csrf
        <h2 class="font-semibold">Catat pemeriksaan / kalibrasi / perbaikan</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="label">Jenis</label><select name="type" x-model="t" class="input">@foreach (FacilityLog::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal</label><input type="date" name="performed_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="input"></div>
            <div><label class="label">Kondisi setelahnya</label><select name="condition_after" class="input"><option value="">Tidak berubah</option>@foreach (Facility::CONDITIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div x-show="t === 'kalibrasi'"><label class="label">Sertifikat kalibrasi berlaku s.d.</label><input type="date" name="valid_until" class="input"></div>
            <div><label class="label">Pemasok / vendor</label><select name="supplier_id" class="input"><option value="">–</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="label">Biaya (Rp)</label><input type="number" name="cost" min="0" class="input"></div>
        </div>
        <textarea name="result" rows="2" required class="input" placeholder="Hasil / temuan / pekerjaan yang dilakukan"></textarea>
        <input type="file" name="evidence[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm">
        <button class="btn-primary w-fit">Simpan</button>
    </form>
    @endcan
</x-app>
