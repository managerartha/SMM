<x-app title="Dokumen baru">
    <x-page-header title="Dokumen baru" subtitle="Nomor dokumen dibuat otomatis oleh sistem saat disimpan (format ASI-[jenis]-[divisi]-[urut])." />
    <form method="post" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="document_type_id">Jenis dokumen *</label>
                <select id="document_type_id" name="document_type_id" required class="input">
                    <option value="">Pilih…</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('document_type_id') == $t->id)>{{ $t->code }} – {{ $t->name }}</option>@endforeach
                </select>
                <x-field-error name="document_type_id" />
            </div>
            <div>
                <label class="label" for="division_id">Divisi pemilik *</label>
                <select id="division_id" name="division_id" required class="input">
                    @if ($divisions->count() > 1)<option value="">Pilih…</option>@endif
                    @foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', auth()->user()->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach
                </select>
                <x-field-error name="division_id" />
            </div>
        </div>
        @include('documents._fields')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="pdf">File PDF (salinan distribusi) *</label>
                <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" required class="block w-full text-sm">
                <p class="help">Wajib PDF, maks. {{ $maxMb }} MB.</p>
                <x-field-error name="pdf" />
            </div>
            <div>
                <label class="label" for="source">File sumber (opsional)</label>
                <input id="source" type="file" name="source" accept=".docx,.xlsx" class="block w-full text-sm">
                <p class="help">DOCX/XLSX. Hanya bisa diunduh GM, MR, Direktur.</p>
                <x-field-error name="source" />
            </div>
        </div>
        <div>
            <label class="label" for="change_summary">Ringkasan perubahan</label>
            <input id="change_summary" name="change_summary" value="{{ old('change_summary', 'Terbit pertama') }}" class="input">
        </div>
        <div class="flex gap-2 pt-2"><button class="btn-primary">Simpan sebagai Draf</button><a href="{{ route('documents.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
