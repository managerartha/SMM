<x-app title="Divisi">
    <x-page-header title="Divisi" subtitle="Kode divisi dipakai di nomor dokumen dan tidak bisa diubah setelah dibuat." />
    <div class="card mb-5 divide-y">
        @foreach ($divisions as $d)
            <form method="post" action="{{ route('admin.divisions.update', $d) }}" class="grid items-center gap-2 p-3 sm:grid-cols-12">@csrf @method('PUT')
                <div class="font-semibold sm:col-span-1">{{ $d->code }}</div>
                <input name="name" value="{{ $d->name }}" class="input sm:col-span-4" aria-label="Nama">
                <select name="head_user_id" class="input sm:col-span-4" aria-label="Kepala divisi"><option value="">Kepala divisi: –</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($d->head_user_id == $u->id)>{{ $u->name }}</option>@endforeach</select>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($d->is_active) class="rounded border-slate-300"> Aktif</label>
                <button class="btn-secondary btn-sm sm:col-span-1">Simpan</button>
            </form>
        @endforeach
    </div>
    <form method="post" action="{{ route('admin.divisions.store') }}" class="card card-body flex max-w-xl flex-wrap items-end gap-2">@csrf
        <div><label class="label">Kode</label><input name="code" maxlength="10" required class="input w-28 uppercase"></div>
        <div class="flex-1"><label class="label">Nama</label><input name="name" required class="input"></div>
        <button class="btn-primary">Tambah divisi</button>
    </form>
</x-app>
