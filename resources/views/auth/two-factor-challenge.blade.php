<x-guest title="Verifikasi dua langkah">
    <div x-data="{ recovery: false }">
        <p class="mb-4 text-sm text-slate-600" x-show="!recovery">Masukkan 6 digit kode dari aplikasi autentikator (Google Authenticator, Microsoft Authenticator, dll.).</p>
        <p class="mb-4 text-sm text-slate-600" x-cloak x-show="recovery">Masukkan salah satu kode pemulihan Anda.</p>
        <form method="post" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf
            <div x-show="!recovery">
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus class="input tracking-widest">
            </div>
            <div x-cloak x-show="recovery">
                <label class="label" for="recovery_code">Kode pemulihan</label>
                <input id="recovery_code" name="recovery_code" autocomplete="one-time-code" class="input">
            </div>
            <x-field-error name="code" />
            <x-field-error name="recovery_code" />
            <button class="btn-primary w-full">Verifikasi</button>
            <button type="button" class="w-full text-center text-sm link" @click="recovery = !recovery" x-text="recovery ? 'Pakai kode autentikator' : 'Pakai kode pemulihan'"></button>
        </form>
    </div>
</x-guest>
