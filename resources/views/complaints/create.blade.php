@php use App\Models\Complaint; @endphp
<x-app title="Catat keluhan / feedback">
    <x-page-header title="Catat keluhan / feedback / saran" subtitle="Nomor (KLH/tahun/urut) dibuat otomatis." />
    <form method="post" action="{{ route('complaints.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4" x-data="{ t: '{{ old('type', 'keluhan') }}' }">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><label class="label">Jenis *</label>
                <div class="flex flex-wrap gap-4 text-sm">@foreach (Complaint::TYPES as $k => $v)<label class="flex items-center gap-2"><input type="radio" name="type" value="{{ $k }}" x-model="t"> {{ $v }}</label>@endforeach</div></div>
            <div><label class="label">Tanggal diterima *</label><input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
        </div>
        @include('complaints._fields')
        <div x-show="t === 'keluhan'"><label class="label">Tingkat keparahan</label><select name="severity" class="input max-w-xs">@foreach (Complaint::SEVERITIES as $k => $v)<option value="{{ $k }}" @selected(old('severity', 'sedang') === $k)>{{ $v }}</option>@endforeach</select>
            <p class="help">Batas tanggapan keluhan otomatis {{ App\Support\Settings::get('complaint.response_days', 3) }} hari setelah diterima.</p></div>
        <div><label class="label">Lampiran (opsional, maks. 5)</label><input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="block w-full text-sm"><p class="help">Tangkapan layar chat, foto, email, dll.</p><x-field-error name="attachments" /></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('complaints.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
