<x-guest title="Masuk">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            <x-field-error name="email" />
        </div>
        <div>
            <label class="label" for="password">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            <x-field-error name="password" />
        </div>
        <button class="btn-primary w-full">Masuk</button>
        <p class="text-center text-sm"><a class="link" href="{{ route('password.request') }}">Lupa kata sandi?</a></p>
    </form>
</x-guest>
