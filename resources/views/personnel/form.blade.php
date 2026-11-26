@php use App\Models\Personnel; $editing = $p->exists; @endphp
<x-app :title="$editing ? 'Ubah '.$p->number : 'Personel baru'">
    <x-page-header :title="$editing ? 'Ubah '.$p->number.' – '.$p->name : 'Personel baru'" :subtitle="$editing ? null : 'Nomor otomatis: TA-xxx (Tenaga Ahli) / TT-xxx (Tenaga Teknis).'" />
    <form method="post" action="{{ $editing ? route('personnel.update', $p) : route('personnel.store') }}" class="card card-body max-w-3xl space-y-4">@csrf @if ($editing) @method('PUT') @endif
        @unless ($editing)<div><label class="label">Jenis *</label><div class="flex gap-4 text-sm">@foreach (Personnel::TYPES as $k => $v)<label class="flex items-center gap-2"><input type="radio" name="type" value="{{ $k }}" @checked(old('type', $p->type) === $k)> {{ $v }}</label>@endforeach</div></div>@endunless
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama lengkap *</label><input name="name" value="{{ old('name', $p->name) }}" required class="input"></div>
            <div><label class="label">Jabatan / kompetensi</label><input name="position" value="{{ old('position', $p->position) }}" class="input" placeholder="Mis. Ahli K3 Umum, Teknisi K3 Listrik"></div>
            <div><label class="label">Status kepegawaian</label><select name="employment" class="input"><option value="">–</option>@foreach (Personnel::EMPLOYMENT as $k => $v)<option value="{{ $k }}" @selected(old('employment', $p->employment) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Mulai bergabung</label><input type="date" name="joined_at" value="{{ old('joined_at', $p->joined_at?->toDateString()) }}" class="input"></div>
            <div><label class="label">Telepon</label><input name="phone" value="{{ old('phone', $p->phone) }}" class="input"></div>
            <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $p->email) }}" class="input"></div>
            <div><label class="label">Akun SMM (opsional)</label><select name="user_id" class="input"><option value="">–</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('user_id', $p->user_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="label">Status</label><select name="status" class="input"><option value="aktif">Aktif</option><option value="nonaktif" @selected(old('status', $p->status) === 'nonaktif')>Nonaktif</option></select></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_trainer" value="1" @checked(old('is_trainer', $p->is_trainer)) class="rounded border-slate-300"> Bertugas sebagai trainer / instruktur</label>
        <div><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes', $p->notes) }}</textarea></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ $editing ? route('personnel.show', $p) : route('personnel.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
