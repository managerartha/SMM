@php use App\Models\Risk; $editing = $risk->exists; @endphp
<x-app :title="$editing ? 'Ubah '.$risk->number : 'Risiko / peluang baru'">
    <x-page-header :title="$editing ? 'Ubah '.$risk->number : 'Catat risiko / peluang'" :subtitle="$editing ? 'Untuk mengubah skor, gunakan Penilaian ulang di halaman detail (agar riwayat tercatat).' : 'Nomor (RP-urut) dibuat otomatis.'" />
    <form method="post" action="{{ $editing ? route('risks.update', $risk) : route('risks.store') }}" class="card card-body max-w-3xl space-y-4"
          x-data="{ l: {{ old('likelihood', $risk->likelihood) }}, i: {{ old('impact', $risk->impact) }}, get s() { return this.l * this.i }, get lv() { return this.s >= 12 ? 'Tinggi' : (this.s >= 6 ? 'Sedang' : 'Rendah') } }">
        @csrf @if ($editing) @method('PUT') @endif
        @unless ($editing)
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Jenis *</label><div class="flex gap-4 text-sm">@foreach (Risk::TYPES as $k => $v)<label class="flex items-center gap-2"><input type="radio" name="type" value="{{ $k }}" @checked(old('type', $risk->type) === $k)> {{ $v }}</label>@endforeach</div></div>
            <div><label class="label">Divisi *</label><select name="division_id" required class="input">@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', $risk->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select><x-field-error name="division_id" /></div>
        </div>
        @endunless
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Proses / kegiatan</label><input name="process" value="{{ old('process', $risk->process) }}" class="input" placeholder="Mis. Penyelenggaraan kelas"></div>
            <div><label class="label">Judul *</label><input name="title" value="{{ old('title', $risk->title) }}" required class="input"><x-field-error name="title" /></div>
        </div>
        <div><label class="label">Uraian</label><textarea name="description" rows="2" class="input">{{ old('description', $risk->description) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Penyebab</label><textarea name="cause" rows="2" class="input">{{ old('cause', $risk->cause) }}</textarea></div>
            <div><label class="label">Dampak / manfaat bila terjadi</label><textarea name="consequence" rows="2" class="input">{{ old('consequence', $risk->consequence) }}</textarea></div>
        </div>
        @unless ($editing)
        <div class="grid items-end gap-4 sm:grid-cols-3">
            <div><label class="label">Kemungkinan (1–5) *</label><select name="likelihood" x-model.number="l" class="input">@foreach ([1 => 'Sangat jarang', 2 => 'Jarang', 3 => 'Mungkin', 4 => 'Sering', 5 => 'Hampir pasti'] as $k => $v)<option value="{{ $k }}">{{ $k }} – {{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Dampak (1–5) *</label><select name="impact" x-model.number="i" class="input">@foreach ([1 => 'Sangat kecil', 2 => 'Kecil', 3 => 'Sedang', 4 => 'Besar', 5 => 'Sangat besar'] as $k => $v)<option value="{{ $k }}">{{ $k }} – {{ $v }}</option>@endforeach</select></div>
            <div class="rounded-md bg-slate-50 p-3 text-sm">Skor <b x-text="s"></b> · Level <b x-text="lv"></b></div>
        </div>
        @endunless
        <div><label class="label">Pengendalian yang sudah ada</label><textarea name="existing_controls" rows="2" class="input">{{ old('existing_controls', $risk->existing_controls) }}</textarea></div>
        <div><label class="label">Rencana penanganan / pemanfaatan</label><textarea name="treatment" rows="2" class="input">{{ old('treatment', $risk->treatment) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label">PIC penanganan</label><select name="treatment_pic_id" class="input"><option value="">–</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('treatment_pic_id', $risk->treatment_pic_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="label">Tenggat penanganan</label><input type="date" name="treatment_due_date" value="{{ old('treatment_due_date', $risk->treatment_due_date?->toDateString()) }}" class="input"></div>
            <div><label class="label">Status penanganan</label><select name="treatment_status" class="input">@foreach (Risk::TREATMENT_STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('treatment_status', $risk->treatment_status ?? 'rencana') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Pemilik risiko *</label><select name="owner_id" required class="input">@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('owner_id', $risk->owner_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="label">Tinjauan berikutnya</label><input type="date" name="review_date" value="{{ old('review_date', $risk->review_date?->toDateString()) }}" class="input"><p class="help">Kosong = 12 bulan lagi.</p></div>
        </div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ $editing ? route('risks.show', $risk) : route('risks.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
