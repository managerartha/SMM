<x-app title="Pembaruan Sistem">
    <x-page-header title="Pembaruan Sistem" subtitle="Versi terpasang: {{ $version }}" />
    <div class="card card-body max-w-3xl space-y-3 text-sm">
        <p>Setelah mengunggah & mengekstrak ZIP versi baru (lihat <em>docs/DEPLOY_HOSTINGER.md</em>), tekan tombol di bawah untuk menjalankan migrasi database, melengkapi data dasar, dan menyegarkan cache. Data yang ada tidak dihapus.</p>
        <pre class="max-h-60 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ trim($status) ?: 'Tidak ada migrasi tertunda.' }}</pre>
        <form method="post" action="{{ route('admin.system.run') }}">@csrf<button class="btn-primary">Jalankan pembaruan</button></form>
        @if (session('output'))<pre class="max-h-80 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ session('output') }}</pre>@endif
    </div>
</x-app>
