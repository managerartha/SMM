@php use App\Support\Format; @endphp
<x-app title="Profil">
    <x-page-header title="Profil & keamanan" />
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card card-body">
            <h2 class="mb-3 font-semibold">Data akun</h2>
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-slate-500">Nama</dt><dd class="col-span-2">{{ $user->name }}</dd>
                <dt class="text-slate-500">Email</dt><dd class="col-span-2">{{ $user->email }}</dd>
                <dt class="text-slate-500">Jabatan</dt><dd class="col-span-2">{{ $user->position ?? '–' }}</dd>
                <dt class="text-slate-500">Divisi</dt><dd class="col-span-2">{{ $user->division?->name ?? '–' }}</dd>
                <dt class="text-slate-500">Peran</dt><dd class="col-span-2">{{ $user->roleLabels() }}</dd>
                <dt class="text-slate-500">Login terakhir</dt><dd class="col-span-2">{{ Format::dateTime($user->last_login_at) }} ({{ $user->last_login_ip }})</dd>
                @if ($user->access_expires_at)<dt class="text-slate-500">Akun berlaku s.d.</dt><dd class="col-span-2">{{ Format::date($user->access_expires_at) }}</dd>@endif
            </dl>
            <p class="help mt-3">Perubahan nama, jabatan, atau divisi dilakukan oleh GM.</p>
        </section>

        <section class="card card-body">
            <h2 class="mb-3 font-semibold">Ganti kata sandi</h2>
            @if (session('status') === 'password-updated')<p class="mb-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Kata sandi diperbarui.</p>@endif
            <form method="post" action="{{ route('user-password.update') }}" class="space-y-3">
                @csrf @method('PUT')
                <div><label class="label">Kata sandi saat ini</label><input type="password" name="current_password" class="input" autocomplete="current-password" required>
                    @error('current_password', 'updatePassword')<p class="err">{{ $message }}</p>@enderror</div>
                <div><label class="label">Kata sandi baru</label><input type="password" name="password" class="input" autocomplete="new-password" required>
                    <p class="help">Minimal 10 karakter, berisi huruf dan angka.</p>
                    @error('password', 'updatePassword')<p class="err">{{ $message }}</p>@enderror</div>
                <div><label class="label">Ulangi kata sandi baru</label><input type="password" name="password_confirmation" class="input" autocomplete="new-password" required></div>
                <button class="btn-primary">Simpan</button>
            </form>
        </section>

        @can('akun.2fa')
        <section class="card card-body lg:col-span-2">
            <h2 class="mb-1 font-semibold">Verifikasi dua langkah (TOTP)</h2>
            <p class="mb-3 text-sm text-slate-600">Setelah aktif, setiap login meminta kode 6 digit dari aplikasi autentikator di HP Anda.</p>
            @php $tf = $user->two_factor_secret; @endphp
            @if (! $tf)
                <form method="post" action="{{ route('two-factor.enable') }}">@csrf<button class="btn-primary">Aktifkan verifikasi dua langkah</button></form>
            @elseif (! $user->two_factor_confirmed_at)
                <p class="mb-2 text-sm">1. Pindai kode QR ini dengan aplikasi autentikator:</p>
                <div class="mb-3 inline-block rounded bg-white p-2 ring-1 ring-slate-200">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <p class="mb-2 text-sm">2. Masukkan kode 6 digit yang muncul untuk mengonfirmasi:</p>
                <form method="post" action="{{ route('two-factor.confirm') }}" class="flex max-w-xs gap-2">@csrf
                    <input name="code" inputmode="numeric" class="input" required><button class="btn-primary">Konfirmasi</button></form>
                @error('code', 'confirmTwoFactorAuthentication')<p class="err">{{ $message }}</p>@enderror
            @else
                <p class="mb-3"><x-badge color="green">Aktif sejak {{ Format::date($user->two_factor_confirmed_at) }}</x-badge></p>
                <details class="mb-3 text-sm"><summary class="cursor-pointer link">Tampilkan kode pemulihan</summary>
                    <p class="my-2 text-slate-600">Simpan kode ini di tempat aman. Setiap kode hanya bisa dipakai sekali bila HP hilang.</p>
                    <div class="grid max-w-md grid-cols-2 gap-1 rounded bg-slate-50 p-3 font-mono text-xs">@foreach ($user->recoveryCodes() as $c)<span>{{ $c }}</span>@endforeach</div>
                    <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-2">@csrf<button class="btn-secondary btn-sm">Buat kode pemulihan baru</button></form>
                </details>
                <form method="post" action="{{ route('two-factor.disable') }}" onsubmit="return confirm('Nonaktifkan verifikasi dua langkah?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Nonaktifkan</button></form>
            @endif
        </section>
        @endcan
    </div>
</x-app>
