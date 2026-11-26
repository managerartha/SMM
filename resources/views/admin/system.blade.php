<x-app title="Pembaruan Sistem">
    <x-page-header title="Pembaruan Sistem" subtitle="Versi terpasang: {{ $version }} · Versi berkas: {{ $fileVersion }}" />
    @if ($pending)<div class="mb-4 max-w-3xl rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">Versi baru sudah diunggah tetapi belum dipasang. Klik <b>Jalankan pembaruan</b>. Sampai selesai, pengguna lain melihat pesan "sedang diperbarui".</div>@endif
    <div class="card card-body max-w-3xl space-y-3 text-sm">
        <p>Setelah mengunggah & mengekstrak ZIP versi baru (lihat <em>docs/DEPLOY_HOSTINGER.md</em>), tekan tombol di bawah untuk menjalankan migrasi database, melengkapi data dasar, dan menyegarkan cache. Data yang ada tidak dihapus.</p>
        <pre class="max-h-60 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ trim($status) ?: 'Tidak ada migrasi tertunda.' }}</pre>
        <form method="post" action="{{ route('admin.system.run') }}">@csrf<button class="btn-primary">Jalankan pembaruan</button></form>
        @if (session('output'))<pre class="max-h-80 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ session('output') }}</pre>@endif
    </div>
</x-app>
