<x-app title="Ubah {{ $c->number }}">
    <x-page-header :title="'Ubah '.$c->number" />
    <form method="post" action="{{ route('complaints.update', $c) }}" class="card card-body max-w-3xl space-y-4">
        @csrf @method('PUT')
        @include('complaints._fields')
        @if ($c->type === 'keluhan')
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Tingkat keparahan</label><select name="severity" class="input">@foreach (App\Models\Complaint::SEVERITIES as $k => $v)<option value="{{ $k }}" @selected(old('severity', $c->severity) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Batas tanggapan</label><input type="date" name="response_due_date" value="{{ old('response_due_date', $c->response_due_date?->toDateString()) }}" class="input"></div>
        </div>
        @endif
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ route('complaints.show', $c) }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
