<x-app title="Program Pelatihan">
    <x-page-header title="Program Pelatihan" subtitle="Data induk program. Kelas dari LMS dicocokkan lewat kode program / ID program LMS / nama." />
    <div class="card mb-5 divide-y">
        @forelse ($programs as $p)
            <form method="post" action="{{ route('admin.programs.update', $p) }}" class="grid items-center gap-2 p-3 sm:grid-cols-12">@csrf @method('PUT')
                <input name="code" value="{{ $p->code }}" class="input sm:col-span-2" aria-label="Kode">
                <input name="name" value="{{ $p->name }}" class="input sm:col-span-4" aria-label="Nama">
                <input name="institution" value="{{ $p->institution }}" class="input sm:col-span-2" placeholder="Lembaga" aria-label="Lembaga">
                <input type="number" name="duration_days" value="{{ $p->duration_days }}" class="input sm:col-span-1" placeholder="Hari" aria-label="Durasi">
                <input name="lms_program_id" value="{{ $p->lms_program_id }}" class="input sm:col-span-1" placeholder="ID LMS" aria-label="ID program LMS">
                <label class="flex items-center gap-1 text-sm sm:col-span-1"><input type="checkbox" name="is_active" value="1" @checked($p->is_active) class="rounded border-slate-300"> Aktif</label>
                <button class="btn-secondary btn-sm sm:col-span-1">Simpan</button>
            </form>
        @empty
            <p class="p-4 text-sm text-slate-500">Belum ada program.</p>
        @endforelse
    </div>
    <form method="post" action="{{ route('admin.programs.store') }}" class="card card-body grid max-w-4xl gap-2 sm:grid-cols-6 sm:items-end">@csrf
        <div><label class="label">Kode</label><input name="code" required class="input" placeholder="AK3U"></div>
        <div class="sm:col-span-2"><label class="label">Nama</label><input name="name" required class="input"></div>
        <div><label class="label">Lembaga</label><input name="institution" class="input" placeholder="Kemnaker RI"></div>
        <div><label class="label">Durasi (hari)</label><input type="number" name="duration_days" class="input"></div>
        <button class="btn-primary">Tambah</button>
    </form>
</x-app>
