<x-app :title="$i->exists ? 'Ubah '.$i->number : 'Usulan Inovasi'">
    <x-page-header :title="$i->exists ? 'Ubah usulan '.$i->number : 'Usulan Program Inovasi Mutu'" subtitle="Inovasi tanpa anggaran disetujui GM; dengan anggaran disetujui Direktur (lihat Matriks Pengesahan)" />
    <form method="post" action="{{ $i->exists ? route('innovations.update', $i) : route('innovations.store') }}" class="card card-body max-w-3xl space-y-3">@csrf
        @if ($i->exists) @method('PUT') @endif
        <div><label class="label">Judul *</label><input name="title" required value="{{ old('title', $i->title) }}" class="input"><x-field-error name="title" /></div>
        <div><label class="label">Latar belakang / masalah *</label><textarea name="background" required rows="3" class="input">{{ old('background', $i->background) }}</textarea><x-field-error name="background" /></div>
        <div><label class="label">Tujuan *</label><textarea name="objective" required rows="2" class="input">{{ old('objective', $i->objective) }}</textarea></div>
        <div><label class="label">Rencana pelaksanaan</label><textarea name="plan" rows="3" class="input">{{ old('plan', $i->plan) }}</textarea></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label">Indikator keberhasilan *</label><input name="indicator" required value="{{ old('indicator', $i->indicator) }}" class="input" placeholder="Mis. waktu terbit sertifikat (hari)"></div>
            <div><label class="label">Kondisi awal (baseline)</label><input name="baseline" value="{{ old('baseline', $i->baseline) }}" class="input" placeholder="Mis. 14 hari"></div>
            <div><label class="label">Anggaran (Rp)</label><input type="number" min="0" name="budget" value="{{ old('budget', $i->budget ?? 0) }}" class="input"><x-field-error name="budget" /></div>
            <div><label class="label">Divisi</label><select name="division_id" class="input"><option value="">–</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(old('division_id', $i->division_id) == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
            <div><label class="label">PIC pelaksana</label><select name="pic_id" class="input"><option value="">–</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('pic_id', $i->pic_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">Mulai</label><input type="date" name="start_date" value="{{ old('start_date', $i->start_date?->toDateString()) }}" class="input"></div>
                <div><label class="label">Selesai</label><input type="date" name="end_date" value="{{ old('end_date', $i->end_date?->toDateString()) }}" class="input"><x-field-error name="end_date" /></div>
            </div>
        </div>
        <div class="flex gap-2"><button class="btn-primary">{{ $i->exists ? 'Simpan' : 'Ajukan usulan' }}</button><a href="{{ route('innovations.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
