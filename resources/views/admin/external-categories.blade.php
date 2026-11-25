<x-app title="Kategori Dokumen Eksternal">
    <x-page-header title="Kategori Dokumen Eksternal" subtitle="Kode kategori dipakai di nomor internal DE-[kategori]-[urut]." />
    <div class="card mb-5 divide-y">
        @foreach ($categories as $c)
            <form method="post" action="{{ route('admin.external-categories.update', $c) }}" class="grid items-center gap-2 p-3 sm:grid-cols-12">@csrf @method('PUT')
                <div class="font-semibold sm:col-span-1">{{ $c->code }}</div>
                <input name="name" value="{{ $c->name }}" class="input sm:col-span-4" aria-label="Nama">
                <select name="default_evidence_category_id" class="input sm:col-span-4" aria-label="Kategori bukti"><option value="">Kategori bukti: –</option>@foreach ($evidence as $e)<option value="{{ $e->id }}" @selected($c->default_evidence_category_id == $e->id)>{{ $e->label() }}</option>@endforeach</select>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($c->is_active) class="rounded border-slate-300"> Aktif</label>
                <button class="btn-secondary btn-sm sm:col-span-1">Simpan</button>
            </form>
        @endforeach
    </div>
    <form method="post" action="{{ route('admin.external-categories.store') }}" class="card card-body flex max-w-3xl flex-wrap items-end gap-2">@csrf
        <div><label class="label">Kode</label><input name="code" maxlength="10" required class="input w-28 uppercase"></div>
        <div class="flex-1"><label class="label">Nama</label><input name="name" required class="input"></div>
        <div><label class="label">Kategori bukti</label><select name="default_evidence_category_id" class="input"><option value="">–</option>@foreach ($evidence as $e)<option value="{{ $e->id }}">{{ $e->label() }}</option>@endforeach</select></div>
        <button class="btn-primary">Tambah</button>
    </form>
</x-app>
