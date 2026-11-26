@php use App\Models\Facility; $editing = $f->exists; @endphp
<x-app :title="$editing ? 'Ubah '.$f->number : 'Sarana baru'">
    <x-page-header :title="$editing ? 'Ubah '.$f->number : 'Sarana & prasarana baru'" :subtitle="$editing ? null : 'Nomor otomatis: SP-[kategori]-[urut], mis. SP-ALT-012.'" />
    <form method="post" action="{{ $editing ? route('facilities.update', $f) : route('facilities.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4" x-data="{ cal: {{ old('needs_calibration', $f->needs_calibration) ? 'true' : 'false' }} }">@csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            @unless ($editing)<div><label class="label">Kategori *</label><select name="category" required class="input">@foreach ($categories as $k => $v)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $k }} – {{ $v }}</option>@endforeach</select></div>@endunless
            <div><label class="label">Nama *</label><input name="name" value="{{ old('name', $f->name) }}" required class="input"></div>
            <div><label class="label">Merek / tipe</label><input name="brand_model" value="{{ old('brand_model', $f->brand_model) }}" class="input"></div>
            <div><label class="label">Nomor seri</label><input name="serial_number" value="{{ old('serial_number', $f->serial_number) }}" class="input"></div>
            <div><label class="label">Lokasi</label><input name="location" value="{{ old('location', $f->location) }}" class="input"></div>
            <div><label class="label">Jumlah *</label><input type="number" name="quantity" min="1" value="{{ old('quantity', $f->quantity) }}" required class="input"></div>
            <div><label class="label">Kondisi *</label><select name="condition" class="input">@foreach (Facility::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected(old('condition', $f->condition) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal diperoleh</label><input type="date" name="acquired_at" value="{{ old('acquired_at', $f->acquired_at?->toDateString()) }}" class="input"></div>
            <div><label class="label">Interval pemeriksaan (bulan)</label><input type="number" name="inspection_interval_months" min="1" max="60" value="{{ old('inspection_interval_months', $f->inspection_interval_months) }}" class="input"></div>
            <div><label class="label">Pemeriksaan berikutnya</label><input type="date" name="next_inspection_at" value="{{ old('next_inspection_at', $f->next_inspection_at?->toDateString()) }}" class="input"><p class="help">Kosong = hari ini + interval.</p></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_calibration" value="1" x-model="cal" class="rounded border-slate-300"> Perlu kalibrasi berkala</label>
        <div x-show="cal"><label class="label">Jadwal kalibrasi berikutnya</label><input type="date" name="next_calibration_at" value="{{ old('next_calibration_at', $f->next_calibration_at?->toDateString()) }}" class="input max-w-xs"><x-field-error name="next_calibration_at" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Status</label><select name="status" class="input"><option value="aktif">Aktif</option><option value="nonaktif" @selected(old('status', $f->status) === 'nonaktif')>Nonaktif</option></select></div>
            <div><label class="label">{{ $editing ? 'Tambah foto' : 'Foto' }}</label><input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm"></div>
        </div>
        <div><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes', $f->notes) }}</textarea></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ $editing ? route('facilities.show', $f) : route('facilities.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
