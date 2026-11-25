<x-guest title="Lupa kata sandi">
    <p class="mb-4 text-sm text-slate-600">Masukkan email Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
            <x-field-error name="email" />
        </div>
        <button class="btn-primary w-full">Kirim tautan</button>
        <p class="text-center text-sm"><a class="link" href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
    </form>
</x-guest>
