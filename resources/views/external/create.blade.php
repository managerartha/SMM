<x-app title="Tambah dokumen eksternal">
    <x-page-header title="Tambah dokumen eksternal" subtitle="Nomor internal (DE-[kategori]-[urut]) dibuat otomatis." />
    <form method="post" action="{{ route('external-documents.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="cat">Kategori *</label>
                <select id="cat" name="external_document_category_id" required class="input"><option value="">Pilih…</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('external_document_category_id') == $c->id)>{{ $c->code }} – {{ $c->name }}</option>@endforeach</select>
                <x-field-error name="external_document_category_id" />
            </div>
            <div>
                <label class="label" for="evidence_category_id">Kategori bukti</label>
                <select id="evidence_category_id" name="evidence_category_id" class="input"><option value="">Otomatis sesuai kategori</option>
                    @foreach ($evidence as $e)<option value="{{ $e->id }}" @selected(old('evidence_category_id') == $e->id)>{{ $e->label() }}</option>@endforeach</select>
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="title">Judul *</label>
                <input id="title" name="title" value="{{ old('title') }}" required class="input" placeholder="Mis. SKP PJK3 Bidang Pembinaan">
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="issuer">Penerbit / pihak kedua *</label>
                <input id="issuer" name="issuer" value="{{ old('issuer') }}" required class="input">
            </div>
        </div>
        @include('external._version')
        <div><label class="label" for="notes">Catatan</label><textarea id="notes" name="notes" rows="2" class="input">{{ old('notes') }}</textarea></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('external-documents.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
