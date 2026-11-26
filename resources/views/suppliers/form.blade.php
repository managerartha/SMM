@php use App\Models\Supplier; $editing = $s->exists; @endphp
<x-app :title="$editing ? 'Ubah '.$s->number : 'Pemasok baru'">
    <x-page-header :title="$editing ? 'Ubah '.$s->number.' – '.$s->name : 'Pemasok baru'" />
    <form method="post" action="{{ $editing ? route('suppliers.update', $s) : route('suppliers.store') }}" class="card card-body max-w-3xl space-y-4">@csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama *</label><input name="name" value="{{ old('name', $s->name) }}" required class="input"></div>
            <div><label class="label">Kategori *</label><select name="category" class="input">@foreach (Supplier::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $s->category) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="label">Kontak</label><input name="contact_person" value="{{ old('contact_person', $s->contact_person) }}" class="input"></div>
            <div><label class="label">Telepon</label><input name="phone" value="{{ old('phone', $s->phone) }}" class="input"></div>
            <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $s->email) }}" class="input"></div>
            <div><label class="label">Status</label><select name="status" class="input">@foreach (Supplier::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $s->status) === $k)>{{ $v }}</option>@endforeach</select></div>
        </div>
        <div><label class="label">Alamat</label><textarea name="address" rows="2" class="input">{{ old('address', $s->address) }}</textarea></div>
        <div><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes', $s->notes) }}</textarea></div>
        <div class="flex gap-2"><button class="btn-primary">Simpan</button><a href="{{ $editing ? route('suppliers.show', $s) : route('suppliers.index') }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
