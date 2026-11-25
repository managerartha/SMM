{{-- Isian bersama untuk buat & ubah draf. $document opsional. --}}
@php $doc = $document ?? null; $scope = old('access_scope', $doc?->access_scope ?? 'terbatas');
     $selDiv = old('access_divisions', $doc?->accessRules->whereNotNull('division_id')->pluck('division_id')->all() ?? []);
     $selRole = old('access_roles', $doc?->accessRules->whereNotNull('role_name')->pluck('role_name')->all() ?? []); @endphp
<div>
    <label class="label" for="title">Judul dokumen *</label>
    <input id="title" name="title" value="{{ old('title', $doc?->title) }}" required maxlength="255" class="input">
    <x-field-error name="title" />
</div>
<div>
    <label class="label" for="summary">Ringkasan</label>
    <textarea id="summary" name="summary" rows="3" class="input" placeholder="Ruang lingkup / isi singkat (dipakai juga untuk pencarian)">{{ old('summary', $doc?->summary) }}</textarea>
</div>
<div>
    <label class="label" for="evidence_category_id">Kategori bukti (Paket Bukti 1–16)</label>
    <select id="evidence_category_id" name="evidence_category_id" class="input">
        <option value="">Otomatis sesuai jenis dokumen</option>
        @foreach ($evidence as $e)<option value="{{ $e->id }}" @selected(old('evidence_category_id', $doc?->evidence_category_id) == $e->id)>{{ $e->label() }}</option>@endforeach
    </select>
</div>
<fieldset x-data="{ scope: '{{ $scope }}' }" class="rounded-md border border-slate-200 p-3">
    <legend class="px-1 text-sm font-medium text-slate-700">Cakupan akses (siapa yang boleh melihat versi berlaku)</legend>
    <div class="flex flex-col gap-2 text-sm sm:flex-row sm:gap-6">
        <label class="flex items-center gap-2"><input type="radio" name="access_scope" value="umum" x-model="scope"> Umum – semua pengguna</label>
        <label class="flex items-center gap-2"><input type="radio" name="access_scope" value="terbatas" x-model="scope"> Terbatas – divisi pemilik + pilihan di bawah</label>
    </div>
    <div x-show="scope === 'terbatas'" class="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Divisi lain yang boleh melihat</p>
            <div class="grid grid-cols-2 gap-1 text-sm">
                @foreach ($allDivisions as $d)<label class="flex items-center gap-2"><input type="checkbox" name="access_divisions[]" value="{{ $d->id }}" @checked(in_array($d->id, $selDiv)) class="rounded border-slate-300"> {{ $d->code }}</label>@endforeach
            </div>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Peran yang boleh melihat</p>
            <div class="space-y-1 text-sm">
                @foreach (['trainer', 'staf', 'kadiv'] as $r)<label class="flex items-center gap-2"><input type="checkbox" name="access_roles[]" value="{{ $r }}" @checked(in_array($r, $selRole)) class="rounded border-slate-300"> {{ $roles[$r] }}</label>@endforeach
            </div>
        </div>
    </div>
    <p class="help">GM, Direktur, MR, Kepala Divisi, dan Auditor selalu bisa melihat semua dokumen berlaku.</p>
</fieldset>
