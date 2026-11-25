@php $editing = $user->exists; $selRoles = old('roles', $editing ? $user->getRoleNames()->all() : []); @endphp
<x-app :title="$editing ? 'Ubah pengguna' : 'Pengguna baru'">
    <x-page-header :title="$editing ? 'Ubah pengguna: '.$user->name : 'Pengguna baru'" />
    <form method="post" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama lengkap *</label><input name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
            <div><label class="label">Email *</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
            <div><label class="label">Jabatan</label><input name="position" value="{{ old('position', $user->position) }}" class="input" placeholder="Dicatat pada bukti pengesahan"></div>
            <div><label class="label">Divisi</label><select name="division_id" class="input"><option value="">–</option>
                @foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', $user->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
        </div>
        <fieldset>
            <legend class="label">Peran * <span class="font-normal text-slate-500">(boleh lebih dari satu, mis. GM merangkap MR)</span></legend>
            <div class="grid gap-1 text-sm sm:grid-cols-2">
                @foreach ($roles as $key => $label)<label class="flex items-center gap-2"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $selRoles)) class="rounded border-slate-300"> {{ $label }}</label>@endforeach
            </div>
            <x-field-error name="roles" />
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Masa berlaku akun</label><input type="date" name="access_expires_at" value="{{ old('access_expires_at', $user->access_expires_at?->format('Y-m-d')) }}" class="input">
                <p class="help">Wajib untuk auditor. Kosongkan untuk akun tetap.</p><x-field-error name="access_expires_at" /></div>
            <div><label class="label">{{ $editing ? 'Kata sandi baru (opsional)' : 'Kata sandi awal (opsional)' }}</label><input type="password" name="password" autocomplete="new-password" class="input">
                <p class="help">Kosongkan bila pengguna akan membuat sendiri lewat "Lupa kata sandi". Minimal 10 karakter, huruf & angka.</p><x-field-error name="password" /></div>
        </div>
        <div class="flex flex-wrap gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a></div>
    </form>
    @if ($editing && $user->two_factor_secret)
        <form method="post" action="{{ route('admin.users.reset-2fa', $user) }}" class="mt-4" onsubmit="return confirm('Reset verifikasi dua langkah pengguna ini (mis. HP hilang)?')">@csrf<button class="btn-secondary btn-sm">Reset verifikasi dua langkah</button></form>
    @endif
</x-app>
