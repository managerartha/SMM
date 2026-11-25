<div class="grid gap-4 sm:grid-cols-3">
    <div class="sm:col-span-3">
        <label class="label" for="original_number">Nomor asli dari penerbit *</label>
        <input id="original_number" name="original_number" value="{{ old('original_number') }}" required class="input">
        <x-field-error name="original_number" />
    </div>
    <div>
        <label class="label" for="issued_at">Tanggal terbit *</label>
        <input id="issued_at" type="date" name="issued_at" value="{{ old('issued_at') }}" required class="input">
        <x-field-error name="issued_at" />
    </div>
    <div>
        <label class="label" for="expires_at">Tanggal berakhir</label>
        <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at') }}" class="input">
        <p class="help">Kosongkan bila tidak ada masa berlaku (mis. akta).</p>
        <x-field-error name="expires_at" />
    </div>
    <div>
        <label class="label" for="file">Berkas *</label>
        <input id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="block w-full text-sm">
        <p class="help">PDF/JPG/PNG/DOCX/XLSX, maks. {{ $maxMb }} MB.</p>
        <x-field-error name="file" />
    </div>
    <div class="sm:col-span-3">
        <label class="label" for="version_notes">Catatan versi</label>
        <input id="version_notes" name="version_notes" value="{{ old('version_notes') }}" class="input">
    </div>
    @can('legalitas.verifikasi')
    <label class="flex items-start gap-2 text-sm sm:col-span-3"><input type="checkbox" name="verified" value="1" @checked(old('verified')) class="mt-0.5 rounded border-slate-300">
        <span>Saya telah memeriksa keaslian dokumen ini terhadap dokumen asli (verifikasi GM). Nama dan tanggal Anda akan dicatat.</span></label>
    @endcan
</div>
