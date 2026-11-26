@php use App\Models\Complaint; $c = $c ?? null; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label">Sumber *</label><select name="source" required class="input">@foreach (Complaint::SOURCES as $k => $v)<option value="{{ $k }}" @selected(old('source', $c?->source) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label">Saluran</label><select name="channel" class="input"><option value="">–</option>@foreach (Complaint::CHANNELS as $k => $v)<option value="{{ $k }}" @selected(old('channel', $c?->channel) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label">Nama pelapor</label><input name="reporter_name" value="{{ old('reporter_name', $c?->reporter_name) }}" class="input"></div>
    <div><label class="label">Kontak pelapor (HP/email)</label><input name="reporter_contact" value="{{ old('reporter_contact', $c?->reporter_contact) }}" class="input"></div>
    <div><label class="label">Perusahaan / instansi</label><input name="reporter_org" value="{{ old('reporter_org', $c?->reporter_org) }}" class="input"></div>
    <div><label class="label">Kode kelas (LMS)</label><input name="class_code" value="{{ old('class_code', $c?->class_code) }}" class="input" placeholder="Kode batch di LMS, bila terkait kelas"></div>
    <div><label class="label">Kategori</label><select name="category" class="input"><option value="">–</option>@foreach (Complaint::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $c?->category) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label">Divisi terkait</label><select name="division_id" class="input"><option value="">–</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', $c?->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
</div>
<div><label class="label">Perihal *</label><input name="subject" value="{{ old('subject', $c?->subject) }}" required maxlength="255" class="input"><x-field-error name="subject" /></div>
<div><label class="label">Uraian *</label><textarea name="description" rows="4" required class="input">{{ old('description', $c?->description) }}</textarea><x-field-error name="description" /></div>
