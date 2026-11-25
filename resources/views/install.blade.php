<x-guest title="Instalasi">
    <h2 class="mb-3 font-semibold">Instalasi SMM</h2>
    <ul class="mb-4 space-y-1 text-sm">
        @foreach ($checks as [$label, $ok])
            <li class="flex items-start gap-2"><span class="{{ $ok ? 'text-emerald-600' : 'text-red-600' }}">{{ $ok ? '✔' : '✘' }}</span><span>{{ $label }}</span></li>
        @endforeach
    </ul>
    @error('install')<p class="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
    @if (! $tokenSet)
        <p class="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">Isi <code>SMM_INSTALL_TOKEN</code> di berkas <code>.env</code> (minimal 16 karakter acak), lalu muat ulang halaman ini.</p>
    @else
    <form method="post" action="{{ route('install.run') }}" class="space-y-3">
        @csrf
        <div><label class="label">Token instalasi (dari .env)</label><input type="password" name="token" required class="input"></div>
        <p class="pt-2 text-sm font-medium">Akun General Manager pertama</p>
        <div><label class="label">Nama</label><input name="name" value="{{ old('name') }}" required class="input"></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <div><label class="label">Jabatan</label><input name="position" value="{{ old('position', 'General Manager') }}" class="input"></div>
        <div><label class="label">Kata sandi</label><input type="password" name="password" required class="input"><x-field-error name="password" /></div>
        <div><label class="label">Ulangi kata sandi</label><input type="password" name="password_confirmation" required class="input"></div>
        <button class="btn-primary w-full">Pasang sekarang</button>
    </form>
    @endif
</x-guest>
