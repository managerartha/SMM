<x-guest title="Kata sandi baru">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required class="input">
            <x-field-error name="email" />
        </div>
        <div>
            <label class="label" for="password">Kata sandi baru</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
            <p class="help">Minimal 10 karakter, berisi huruf dan angka.</p>
            <x-field-error name="password" />
        </div>
        <div>
            <label class="label" for="password_confirmation">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
        </div>
        <button class="btn-primary w-full">Simpan kata sandi</button>
    </form>
</x-guest>
