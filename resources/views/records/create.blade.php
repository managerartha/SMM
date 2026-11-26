<x-app title="Catatan mutu baru">
    <x-page-header title="Catatan mutu baru" subtitle="Nomor otomatis: [kode formulir]/[tahun]/[urut], mis. F-OPS-01/2027/0045 (tanpa formulir: CM/2027/0001)." />
    <form method="post" action="{{ route('records.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">@csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Formulir yang diisi</label><select name="document_id" class="input"><option value="">– Tanpa formulir (foto, notulen, dll.) –</option>@foreach ($forms as $f)<option value="{{ $f->id }}" @selected(old('document_id') == $f->id)>{{ $f->number }} – {{ $f->title }}</option>@endforeach</select><x-field-error name="document_id" /></div>
            <div><label class="label">Kelas (kode LMS)</label><select name="training_class_id" class="input"><option value="">– Bukan kegiatan kelas –</option>@foreach ($classes as $c)<option value="{{ $c->id }}" @selected(old('training_class_id', $selectedClass) == $c->id)>{{ $c->class_code }} – {{ $c->program_name }}</option>@endforeach</select></div>
            <div><label class="label">Divisi *</label><select name="division_id" required class="input">@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', auth()->user()->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal kegiatan *</label><input type="date" name="activity_date" value="{{ old('activity_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
        </div>
        <div><label class="label">Judul *</label><input name="title" value="{{ old('title') }}" required class="input" placeholder="Mis. Daftar hadir hari 1–5 / Laporan trainer / Foto praktik APAR"><x-field-error name="title" /></div>
        <div><label class="label">Keterangan</label><textarea name="description" rows="2" class="input">{{ old('description') }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Masa simpan (tahun)</label><input type="number" name="retention_years" value="{{ old('retention_years', $retention) }}" min="1" max="30" class="input"></div>
            <div><label class="label">Kategori bukti</label><select name="evidence_category_id" class="input">@foreach ($evidence as $e)<option value="{{ $e->id }}" @selected(old('evidence_category_id', 8) == $e->id)>{{ $e->label() }}</option>@endforeach</select></div>
        </div>
        <div><label class="label">Berkas bukti * (maks. 20)</label><input type="file" name="attachments[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="block w-full text-sm"><x-field-error name="attachments" /><p class="help">Scan formulir, foto kegiatan, notulen, dll. Disimpan di Google Drive.</p></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('records.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
