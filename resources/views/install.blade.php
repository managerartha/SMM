<x-guest title="Instalasi">
    <h2 class="mb-1 font-semibold">Instalasi SMM</h2>
    <p class="mb-3 text-xs text-slate-500">Isi sekali saja. Setelah selesai halaman ini terkunci.</p>
    @php $allOk = collect($checks)->every(fn ($c) => $c[1]); @endphp
    <ul class="mb-4 space-y-1 text-sm">
        @foreach ($checks as [$label, $ok])
            <li class="flex items-start gap-2"><span class="{{ $ok ? 'text-emerald-600' : 'text-red-600' }}">{{ $ok ? '✔' : '✘' }}</span><span>{{ $label }}</span></li>
        @endforeach
    </ul>
    @unless ($allOk)
        <p class="mb-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">Perbaiki baris bertanda ✘ dulu (lihat panduan), lalu muat ulang halaman ini.</p>
    @endunless
    @error('install')<p class="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>@enderror

    <form method="post" action="{{ route('install.run') }}" class="space-y-3">
        @csrf
        @if ($tokenRequired)
            <div><label class="label">Token instalasi</label><input type="password" name="token" required class="input"><x-field-error name="token" /></div>
        @endif

        <p class="pt-1 text-sm font-semibold">1. Database</p>
        <p class="text-xs text-slate-500">Dari hPanel → Databases. Pakai nama <b>lengkap</b>, mis. <code>u123456789_smm</code>.</p>
        <div><label class="label">Nama database</label><input name="db_database" value="{{ old('db_database') }}" required class="input"><x-field-error name="db_database" /></div>
        <div><label class="label">User database</label><input name="db_username" value="{{ old('db_username') }}" required class="input"><x-field-error name="db_username" /></div>
        <div><label class="label">Kata sandi database</label><input type="password" name="db_password" required class="input"></div>

        <p class="pt-2 text-sm font-semibold">2. Email pengirim notifikasi <span class="font-normal text-slate-500">(boleh diisi nanti)</span></p>
        <p class="text-xs text-slate-500">Akun email dari hPanel → Emails, mis. <code>mutu@arthasafetyindonesia.com</code>.</p>
        <div><label class="label">Alamat email</label><input type="email" name="mail_username" value="{{ old('mail_username') }}" class="input"><x-field-error name="mail_username" /></div>
        <div><label class="label">Kata sandi email</label><input type="password" name="mail_password" class="input"><x-field-error name="mail_password" /></div>

        <p class="pt-2 text-sm font-semibold">3. Kata sandi backup</p>
        <p class="text-xs text-slate-500">Mengunci berkas backup di Google Drive. <b>Simpan di tempat aman di luar server</b>; tanpa ini backup tidak bisa dipulihkan.</p>
        <div><input name="backup_password" value="{{ old('backup_password', $suggestedPassword) }}" required minlength="12" class="input font-mono"><x-field-error name="backup_password" /></div>

        <p class="pt-2 text-sm font-semibold">4. Akun General Manager</p>
        <div><label class="label">Nama</label><input name="name" value="{{ old('name') }}" required class="input"></div>
        <div><label class="label">Email login</label><input type="email" name="email" value="{{ old('email') }}" required class="input"><x-field-error name="email" /></div>
        <div><label class="label">Jabatan</label><input name="position" value="{{ old('position', 'General Manager') }}" class="input"></div>
        <div><label class="label">Kata sandi</label><input type="password" name="password" required class="input"><p class="help">Minimal 10 karakter, huruf dan angka.</p><x-field-error name="password" /></div>
        <div><label class="label">Ulangi kata sandi</label><input type="password" name="password_confirmation" required class="input"></div>

        <button class="btn-primary w-full" @disabled(! $allOk)>Pasang sekarang</button>
        <p class="text-center text-xs text-slate-500">Proses ± 30 detik. Jangan tutup halaman.</p>
    </form>
</x-guest>
