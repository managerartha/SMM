<x-guest title="Konfirmasi kata sandi">
    <p class="mb-4 text-sm text-slate-600">Demi keamanan, masukkan kembali kata sandi Anda untuk melanjutkan.</p>
    <form method="post" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="password">Kata sandi</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password" class="input">
            <x-field-error name="password" />
        </div>
        <button class="btn-primary w-full">Lanjutkan</button>
    </form>
</x-guest>
