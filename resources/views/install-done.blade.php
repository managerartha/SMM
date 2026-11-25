<x-guest title="Instalasi selesai">
    <h2 class="mb-3 font-semibold text-emerald-700">✔ Instalasi selesai</h2>
    <ol class="list-decimal space-y-4 pl-5 text-sm">
        <li>
            <p class="font-medium">Simpan kata sandi backup ini di tempat aman (bukan di server):</p>
            <p class="mt-1 select-all break-all rounded bg-slate-100 px-2 py-1 font-mono">{{ $backupPassword }}</p>
        </li>
        <li>
            <p class="font-medium">Pasang satu cron di hPanel → Advanced → Cron Jobs (setiap menit):</p>
            <p class="mt-1 select-all break-all rounded bg-slate-100 px-2 py-1 font-mono text-xs">{{ $cron }}</p>
            <p class="help">Bila Hostinger meminta "perintah PHP" terpisah, isi bagian setelah <code>php</code> saja.</p>
        </li>
        <li><p class="font-medium">Masuk dengan {{ $email }}, lalu buka Admin → Google Drive untuk menghubungkan Drive.</p></li>
    </ol>
    <a href="{{ route('login') }}" class="btn-primary mt-5 w-full">Masuk ke SMM</a>
</x-guest>
