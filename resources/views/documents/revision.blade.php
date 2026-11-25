<x-app title="Revisi baru">
    <x-page-header title="Revisi baru {{ $document->number }}" subtitle="Dibuat dari revisi berlaku {{ $document->currentRevision->label() }}. Nomor dokumen tetap; nomor revisi naik otomatis." />
    <form method="post" action="{{ route('documents.revisions.store', $document) }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div>
            <label class="label" for="change_reason">Alasan perubahan *</label>
            <textarea id="change_reason" name="change_reason" rows="3" required class="input" placeholder="Mis. temuan audit, perubahan regulasi, perbaikan proses">{{ old('change_reason') }}</textarea>
            <x-field-error name="change_reason" />
        </div>
        <div>
            <label class="label" for="change_summary">Ringkasan perubahan *</label>
            <input id="change_summary" name="change_summary" value="{{ old('change_summary') }}" required class="input" placeholder="Mis. menambah bagian 6.4 Evaluasi trainer">
            <x-field-error name="change_summary" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="pdf">File PDF revisi baru *</label>
                <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" required class="block w-full text-sm">
                <p class="help">Maks. {{ $maxMb }} MB.</p>
                <x-field-error name="pdf" />
            </div>
            <div>
                <label class="label" for="source">File sumber (opsional)</label>
                <input id="source" type="file" name="source" accept=".docx,.xlsx" class="block w-full text-sm">
                <x-field-error name="source" />
            </div>
        </div>
        <div class="flex gap-2 pt-2"><button class="btn-primary">Buat draf revisi</button><a href="{{ route('documents.show', $document) }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
