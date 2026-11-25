<x-app title="Jenis Dokumen">
    <x-page-header title="Jenis Dokumen" subtitle="Kode jenis dipakai di nomor dokumen. Pengesah tiap jenis diatur di Matriks Pengesahan." />
    <div class="card mb-5 divide-y">
        @foreach ($types as $t)
            <form method="post" action="{{ route('admin.document-types.update', $t) }}" class="grid items-center gap-2 p-3 sm:grid-cols-12">@csrf @method('PUT')
                <div class="font-semibold sm:col-span-1">{{ $t->code }}</div>
                <input name="name" value="{{ $t->name }}" class="input sm:col-span-3" aria-label="Nama">
                <label class="flex items-center gap-1 text-sm sm:col-span-2"><input type="number" name="review_interval_months" value="{{ $t->review_interval_months }}" min="1" max="60" class="input w-16"> bln tinjauan</label>
                <select name="default_evidence_category_id" class="input sm:col-span-3" aria-label="Kategori bukti"><option value="">Kategori bukti: –</option>@foreach ($evidence as $e)<option value="{{ $e->id }}" @selected($t->default_evidence_category_id == $e->id)>{{ $e->label() }}</option>@endforeach</select>
                <div class="flex gap-3 text-sm sm:col-span-2">
                    <label class="flex items-center gap-1"><input type="checkbox" name="review_required" value="1" @checked($t->review_required) class="rounded border-slate-300"> Review</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($t->is_active) class="rounded border-slate-300"> Aktif</label>
                </div>
                <button class="btn-secondary btn-sm sm:col-span-1">Simpan</button>
            </form>
        @endforeach
    </div>
    <form method="post" action="{{ route('admin.document-types.store') }}" class="card card-body grid max-w-3xl gap-3 sm:grid-cols-5 sm:items-end">@csrf
        <div><label class="label">Kode</label><input name="code" maxlength="10" required class="input uppercase"></div>
        <div class="sm:col-span-2"><label class="label">Nama</label><input name="name" required class="input"></div>
        <div><label class="label">Tinjauan (bulan)</label><input type="number" name="review_interval_months" value="12" min="1" max="60" class="input"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="review_required" value="1" checked class="rounded border-slate-300"> Review wajib</label>
        <div class="sm:col-span-5"><button class="btn-primary">Tambah jenis dokumen</button></div>
    </form>
</x-app>
