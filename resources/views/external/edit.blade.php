<x-app title="Ubah informasi">
    <x-page-header title="Ubah informasi {{ $doc->number }}" subtitle="Untuk perpanjangan / dokumen pengganti gunakan tombol Perpanjang (versi baru)." />
    <form method="post" action="{{ route('external-documents.update', $doc) }}" class="card card-body max-w-3xl space-y-4">
        @csrf @method('PUT')
        <div><label class="label">Judul *</label><input name="title" value="{{ old('title', $doc->title) }}" required class="input"></div>
        <div><label class="label">Penerbit / pihak kedua *</label><input name="issuer" value="{{ old('issuer', $doc->issuer) }}" required class="input"></div>
        <div><label class="label">Kategori bukti</label><select name="evidence_category_id" class="input"><option value="">–</option>
            @foreach ($evidence as $e)<option value="{{ $e->id }}" @selected(old('evidence_category_id', $doc->evidence_category_id) == $e->id)>{{ $e->label() }}</option>@endforeach</select></div>
        <div><label class="label">Catatan</label><textarea name="notes" rows="3" class="input">{{ old('notes', $doc->notes) }}</textarea></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('external-documents.show', $doc) }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
