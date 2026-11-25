@php use App\Support\Format; @endphp
<x-app title="Backup">
    <x-page-header title="Backup ke Google Drive" subtitle="Harian 01.00 WIB (simpan 30) · Bulanan tgl 1 pukul 02.00 (simpan 12) · ZIP terenkripsi AES-256">
        <form method="post" action="{{ route('admin.backup.run') }}">@csrf<button class="btn-primary" @disabled(! $passwordSet)>Jalankan backup sekarang</button></form>
    </x-page-header>
    @unless ($passwordSet)<div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">SMM_BACKUP_PASSWORD di .env belum diisi (minimal 12 karakter). Backup tidak bisa berjalan.</div>@endunless
    <div class="mb-5 card card-body text-sm">
        Backup berhasil terakhir: <strong>{{ $last ? Format::dateTime($last->started_at).' – '.$last->file_name : 'belum ada' }}</strong>.
        Panduan pemulihan: <em>docs/RESTORE.md</em>.
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Status</th><th class="hidden md:table-cell">Berkas</th><th class="hidden md:table-cell">Ukuran</th><th>Keterangan</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($runs as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ Format::dateTime($r->started_at) }}</td>
                    <td>{{ ucfirst($r->type) }}</td>
                    <td><x-badge :color="$r->status === 'berhasil' ? 'green' : ($r->status === 'gagal' ? 'red' : 'blue')">{{ ucfirst($r->status) }}</x-badge></td>
                    <td class="hidden text-xs md:table-cell">{{ $r->file_name }}</td>
                    <td class="hidden whitespace-nowrap md:table-cell">{{ $r->size ? number_format($r->size / 1024, 0, ',', '.').' KB' : '–' }}</td>
                    <td class="text-xs">{{ $r->message }}{{ $r->files_in_manifest !== null ? ' '.$r->files_in_manifest.' berkas di manifest.' : '' }}{{ $r->pruned ? ' '.$r->pruned.' backup lama dihapus.' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada backup.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $runs->links() }}</div>
</x-app>
