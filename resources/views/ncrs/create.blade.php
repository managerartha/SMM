@php use App\Models\Ncr; @endphp
<x-app title="NCR baru">
    <x-page-header title="NCR baru" subtitle="Nomor (NCR/tahun/urut) dibuat otomatis. Untuk keluhan, buat NCR dari halaman keluhan agar tertaut." />
    <form method="post" action="{{ route('ncrs.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Sumber temuan *</label><select name="source" required class="input">@foreach (Ncr::SOURCES as $k => $v)<option value="{{ $k }}" @selected(old('source', 'temuan_internal') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal ditemukan *</label><input type="date" name="found_at" value="{{ old('found_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
        </div>
        <div><label class="label">Judul *</label><input name="title" value="{{ old('title') }}" required class="input"><x-field-error name="title" /></div>
        <div><label class="label">Uraian ketidaksesuaian *</label><textarea name="description" rows="4" required class="input" placeholder="Apa yang terjadi, kapan, di mana, bukti objektifnya">{{ old('description') }}</textarea></div>
        <div><label class="label">Persyaratan yang tidak dipenuhi</label><input name="requirement" value="{{ old('requirement') }}" class="input" placeholder="Mis. ASI-SOP-OPS-01 butir 5.2 / ISO 9001 klausul 8.5.1"></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Divisi *</label><select name="division_id" required class="input"><option value="">Pilih…</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id') == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
            <div><label class="label">Tingkat *</label><select name="severity" class="input"><option value="minor">Minor</option><option value="mayor" @selected(old('severity') === 'mayor')>Mayor</option></select></div>
            <div><label class="label">Penanggung jawab *</label><select name="pic_id" required class="input"><option value="">Pilih…</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('pic_id') == $u->id)>{{ $u->name }} – {{ $u->position }}</option>@endforeach</select><x-field-error name="pic_id" /></div>
            <div><label class="label">Target tindakan selesai *</label><input type="date" name="target_date" value="{{ old('target_date', now()->addDays(30)->toDateString()) }}" required class="input"></div>
        </div>
        <div><label class="label">Bukti temuan (opsional, maks. 5)</label><input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="block w-full text-sm"></div>
        <div class="flex gap-2"><button class="btn-primary">Buat NCR</button><a href="{{ route('ncrs.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
