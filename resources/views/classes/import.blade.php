@php use App\Support\Format; @endphp
<x-app title="Impor dari LMS">
    <x-page-header title="Impor data dari LMS" subtitle="SMM hanya membaca. Data LMS tidak pernah diubah. Data pribadi peserta (nama, HP, email, NIK) diabaikan." />
    @if (session('import_errors'))
        <div class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"><p class="font-medium">Baris yang dilewati:</p>
            <ul class="mt-1 list-inside list-disc">@foreach (array_slice(session('import_errors'), 0, 30) as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="grid gap-5 lg:grid-cols-2">
        <form method="post" action="{{ route('classes.import.run') }}" enctype="multipart/form-data" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Impor berkas CSV / Excel</h2>
            <div class="flex gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="kind" value="kelas" checked> Rekap kelas</label><label class="flex items-center gap-2"><input type="radio" name="kind" value="survei"> Respons survei</label></div>
            <input type="file" name="file" required accept=".csv,.xlsx" class="block w-full text-sm"><x-field-error name="file" />
            <button class="btn-primary">Impor</button>
            <div class="text-xs text-slate-600 space-y-1">
                <p><b>Template:</b> <a class="link" href="{{ route('classes.template', 'kelas') }}">kelas.xlsx</a> · <a class="link" href="{{ route('classes.template', 'survei') }}">survei.xlsx</a></p>
                <p><b>Atau langsung dari LMS:</b> phpMyAdmin → database LMS → tabel <code>batch</code> → Export → CSV. Kolom <code>kode_batch</code>, <code>nama_pelatihan</code>, <code>tgl_mulai</code>, <code>tgl_selesai</code>, <code>jenis_pelatihan</code>, <code>perusahaan_inhouse</code> dikenali otomatis. Jumlah peserta/hadir/lulus bisa ditambahkan lewat template.</p>
                <p>Kode kelas yang sudah ada akan <b>diperbarui</b>, bukan diduplikasi.</p>
            </div>
        </form>
        <section class="card card-body space-y-3 text-sm">
            <h2 class="font-semibold">Sinkron API LMS</h2>
            @if ($apiConfigured)
                <p>API LMS terkonfigurasi. Kelas, survei, dan <b>keluhan</b> ditarik otomatis setiap jam (keluhan langsung masuk ke menu Keluhan &amp; Feedback dengan batas tanggapan).</p>
                <form method="post" action="{{ route('classes.sync') }}">@csrf<button class="btn-secondary">Sinkron sekarang (90 hari terakhir)</button></form>
            @else
                <p class="text-slate-600">Belum aktif. Setelah LMS menyediakan API baca-saja, isi <code>SMM_LMS_API_URL=https://lms.arthasafetyindonesia.com/api/smm</code> dan <code>SMM_LMS_API_TOKEN</code> (sama dengan <code>SMM_API_TOKEN</code> di LMS) di <code>.env</code>. Kontrak API: <em>docs/LMS_ADAPTER.md</em>.</p>
            @endif
        </section>
    </div>
    <section class="card mt-5 overflow-x-auto">
        <h2 class="border-b px-4 py-3 font-semibold">Riwayat impor</h2>
        <table class="table">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Sumber</th><th>Baru</th><th>Diperbarui</th><th>Dilewati</th><th>Oleh</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($imports as $i)
                <tr><td class="whitespace-nowrap">{{ Format::dateTime($i->created_at) }}</td><td>{{ $i->kind }}</td><td>{{ $i->file_name }}</td><td>{{ $i->created_count }}</td><td>{{ $i->updated_count }}</td>
                    <td>{{ $i->skipped_count }}@if ($i->errors)<details class="text-xs"><summary class="cursor-pointer link">detail</summary>{{ implode(' · ', array_slice($i->errors, 0, 10)) }}</details>@endif</td><td>{{ $i->importer?->name ?? 'Sistem' }}</td></tr>
            @empty <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada impor.</td></tr> @endforelse
            </tbody>
        </table>
    </section>
</x-app>
