<x-app title="Ubah draf">
    <x-page-header title="Ubah draf {{ $revision->fullLabel() }}" subtitle="{{ $document->type->name }} · {{ $document->division->name }}" />
    <form method="post" action="{{ route('documents.update', [$document, $revision]) }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf @method('PUT')
        @include('documents._fields')
        @if ($revision->revision_no > 0)
        <div>
            <label class="label" for="change_reason">Alasan perubahan *</label>
            <textarea id="change_reason" name="change_reason" rows="2" required class="input">{{ old('change_reason', $revision->change_reason) }}</textarea>
        </div>
        @endif
        <div>
            <label class="label" for="change_summary">Ringkasan perubahan</label>
            <input id="change_summary" name="change_summary" value="{{ old('change_summary', $revision->change_summary) }}" class="input">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="pdf">Ganti file PDF</label>
                <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" class="block w-full text-sm">
                <p class="help">Saat ini: {{ $revision->pdfFile?->original_name ?? 'belum ada' }}</p>
                <x-field-error name="pdf" />
            </div>
            <div>
                <label class="label" for="source">Ganti file sumber</label>
                <input id="source" type="file" name="source" accept=".docx,.xlsx" class="block w-full text-sm">
                <p class="help">Saat ini: {{ $revision->sourceFile?->original_name ?? 'tidak ada' }}</p>
                <x-field-error name="source" />
            </div>
        </div>
        <p class="help">File lama tetap tersimpan di Google Drive (tidak ditimpa).</p>
        <div class="flex gap-2 pt-2"><button class="btn-primary">Simpan</button><a href="{{ route('documents.show', $document) }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
