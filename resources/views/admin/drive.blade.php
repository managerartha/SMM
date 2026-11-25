@php use App\Support\Format; @endphp
<x-app title="Google Drive">
    <x-page-header title="Google Drive" subtitle="Penyimpanan utama berkas & tujuan backup." />
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card card-body space-y-3 text-sm">
            <h2 class="font-semibold">Koneksi</h2>
            <dl class="space-y-1">
                <div class="flex justify-between"><dt>Mode penyimpanan</dt><dd><x-badge :color="$driver === 'google' ? 'green' : 'amber'">{{ $driver === 'google' ? 'Google Drive' : 'Lokal (uji coba)' }}</x-badge></dd></div>
                @if ($driver === 'google')
                <div class="flex justify-between"><dt>Client ID & secret (.env)</dt><dd>{{ $configured ? 'Terisi' : 'Belum diisi' }}</dd></div>
                <div class="flex justify-between"><dt>Status</dt><dd>{!! $connected ? '<span class="badge bg-emerald-100 text-emerald-800">Terhubung</span> '.e($email) : '<span class="badge bg-red-100 text-red-800">Belum terhubung</span>' !!}</dd></div>
                @endif
                <div class="flex justify-between"><dt>Pemeriksaan terakhir</dt><dd>
                    @if ($health) <x-badge :color="$health['ok'] ? 'green' : 'red'">{{ $health['ok'] ? 'OK' : 'Gagal' }}</x-badge> {{ Format::dateTime($health['at']) }} @else – @endif</dd></div>
                @if (! empty($health['quota']['limit']))
                <div class="flex justify-between"><dt>Kuota terpakai</dt><dd>{{ number_format($health['quota']['usage'] / 1073741824, 1, ',', '.') }} / {{ number_format($health['quota']['limit'] / 1073741824, 0, ',', '.') }} GB</dd></div>
                @endif
                @if (! empty($health['error']))<p class="text-xs text-red-600">{{ $health['error'] }}</p>@endif
            </dl>
            @if ($driver === 'google')
                <p class="text-xs text-slate-500">URI pengalihan OAuth (isi di Google Cloud Console): <code class="break-all">{{ $redirect }}</code></p>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="{{ route('admin.drive.connect') }}">@csrf<button class="btn-primary" @disabled(! $configured)>{{ $connected ? 'Hubungkan ulang' : 'Hubungkan Google Drive' }}</button></form>
                    @if ($connected)<form method="post" action="{{ route('admin.drive.disconnect') }}" onsubmit="return confirm('Putus koneksi? Unggahan & backup akan berhenti sampai dihubungkan lagi.')">@csrf<button class="btn-secondary">Putuskan</button></form>@endif
                </div>
            @endif
            <form method="post" action="{{ route('admin.drive.test') }}">@csrf<button class="btn-secondary">Uji koneksi & siapkan folder</button></form>
        </section>
        <section class="card card-body text-sm">
            <h2 class="mb-3 font-semibold">Berkas</h2>
            <dl class="space-y-1">
                <div class="flex justify-between"><dt>Tersimpan di Drive</dt><dd>{{ $stored }}</dd></div>
                <div class="flex justify-between"><dt>Menunggu dipindah</dt><dd>{{ $pending }}</dd></div>
                <div class="flex justify-between"><dt>Gagal</dt><dd class="{{ $failed->count() ? 'font-semibold text-red-600' : '' }}">{{ $failed->count() }}</dd></div>
            </dl>
            @foreach ($failed->take(10) as $f)<p class="mt-2 text-xs text-red-700">{{ $f->drive_name }}: {{ \Illuminate\Support\Str::limit($f->last_error, 160) }}</p>@endforeach
            <p class="help mt-3">Berkas gagal dicoba ulang otomatis. Untuk mencoba ulang paksa: <code>php artisan smm:files-push --failed</code> (lewat cron).</p>
        </section>
    </div>
</x-app>
