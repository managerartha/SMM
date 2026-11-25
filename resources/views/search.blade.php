@php use App\Support\Format; @endphp
<x-app title="Pencarian">
    <x-page-header title="Pencarian" />
    <form method="get" class="mb-5 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" autofocus placeholder="Nomor, judul, ringkasan, penerbit, atau nama…" class="input">
        <button class="btn-primary">Cari</button>
    </form>
    @if (mb_strlen($q) >= 2)
        <div class="space-y-5">
            <section class="card">
                <h2 class="border-b px-4 py-3 text-sm font-semibold">Dokumen terkendali ({{ $results['documents']->count() }})</h2>
                <ul class="divide-y text-sm">
                    @forelse ($results['documents'] as $d)
                        <li class="px-4 py-2.5"><a class="link font-medium" href="{{ route('documents.show', $d) }}">{{ $d->number }}</a> – {{ $d->title }} <span class="ml-1 text-xs text-slate-500">{{ $d->statusLabel() }}</span>
                            @if ($d->summary)<p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($d->summary, 140) }}</p>@endif</li>
                    @empty <li class="px-4 py-4 text-slate-500">Tidak ditemukan.</li> @endforelse
                </ul>
            </section>
            @can('viewAny', App\Models\ExternalDocument::class)
            <section class="card">
                <h2 class="border-b px-4 py-3 text-sm font-semibold">Legalitas & kontrak ({{ $results['external']->count() }})</h2>
                <ul class="divide-y text-sm">
                    @forelse ($results['external'] as $d)
                        <li class="px-4 py-2.5"><a class="link font-medium" href="{{ route('external-documents.show', $d) }}">{{ $d->number }}</a> – {{ $d->title }} <span class="text-xs text-slate-500">({{ $d->issuer }}; no. asli {{ $d->currentVersion?->original_number }})</span></li>
                    @empty <li class="px-4 py-4 text-slate-500">Tidak ditemukan.</li> @endforelse
                </ul>
            </section>
            @endcan
            @can('pengguna.kelola')
            <section class="card">
                <h2 class="border-b px-4 py-3 text-sm font-semibold">Pengguna ({{ $results['users']->count() }})</h2>
                <ul class="divide-y text-sm">
                    @forelse ($results['users'] as $u)
                        <li class="px-4 py-2.5"><a class="link" href="{{ route('admin.users.edit', $u) }}">{{ $u->name }}</a> <span class="text-xs text-slate-500">{{ $u->email }}</span></li>
                    @empty <li class="px-4 py-4 text-slate-500">Tidak ditemukan.</li> @endforelse
                </ul>
            </section>
            @endcan
        </div>
    @elseif ($q !== '')
        <p class="text-sm text-slate-500">Ketik minimal 2 karakter.</p>
    @endif
</x-app>
