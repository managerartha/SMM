<x-app title="Pengaturan">
    <x-page-header title="Pengaturan" />
    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <section class="card card-body grid gap-4 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Unggahan & tanda air</h2>
            <div><label class="label">Ukuran maksimum unggahan (MB)</label><input type="number" name="upload_max_mb" value="{{ $s['upload.max_mb'] }}" min="1" max="100" class="input">
                <p class="help">Batas PHP di hosting (upload_max_filesize & post_max_size) harus ≥ nilai ini.</p></div>
            <label class="flex items-start gap-2 text-sm sm:mt-6"><input type="checkbox" name="watermark_controlled_copy" value="1" @checked($s['watermark.controlled_copy']) class="mt-0.5 rounded border-slate-300">
                <span>Cap "SALINAN TERKENDALI – nama pengunduh – tanggal" pada PDF revisi berlaku</span></label>
        </section>
        <section class="card card-body grid gap-4 sm:grid-cols-3">
            <h2 class="font-semibold sm:col-span-3">Pengingat</h2>
            <div><label class="label">Pengingat legalitas (hari sebelum berakhir)</label><input name="reminder_external_days" value="{{ implode(', ', (array) $s['reminder.external_days']) }}" class="input"><p class="help">Pisahkan dengan koma, mis. 90, 60, 30</p></div>
            <div><label class="label">"Segera berakhir" bila ≤ (hari)</label><input type="number" name="reminder_external_soon_days" value="{{ $s['reminder.external_soon_days'] }}" class="input"></div>
            <div><label class="label">Pengingat tinjauan dokumen (hari sebelumnya)</label><input type="number" name="reminder_review_days" value="{{ $s['reminder.review_days'] }}" class="input"></div>
        </section>
        <section class="card card-body grid gap-4 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Retensi backup di Google Drive</h2>
            <div><label class="label">Backup harian disimpan</label><input type="number" name="backup_daily_retention" value="{{ $s['backup.daily_retention'] }}" class="input"></div>
            <div><label class="label">Backup bulanan disimpan</label><input type="number" name="backup_monthly_retention" value="{{ $s['backup.monthly_retention'] }}" class="input"></div>
        </section>
        <section class="card card-body">
            <h2 class="mb-1 font-semibold">Pola penomoran</h2>
            <p class="mb-3 text-sm text-slate-600">Placeholder: <code>{jenis}</code> <code>{divisi}</code> <code>{kategori}</code> <code>{formulir}</code> <code>{tahun}</code> <code>{bulan}</code> <code>{urut:N}</code> (N = jumlah digit). Pola dengan <code>{tahun}</code> otomatis mulai dari 1 setiap 1 Januari. Nomor yang pernah terpakai tidak akan dipakai ulang walau pola diubah.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($patterns as $key => $label)
                    <div><label class="label">{{ $label }}</label><input name="patterns[{{ $key }}]" value="{{ $s[$key] }}" class="input font-mono"></div>
                @endforeach
            </div>
        </section>
        <button class="btn-primary">Simpan pengaturan</button>
    </form>
</x-app>
