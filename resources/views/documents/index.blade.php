@php use App\Support\Format; @endphp
<x-app title="Dokumen Terkendali">
    <x-page-header title="Dokumen Terkendali" subtitle="Manual, kebijakan, SOP, instruksi kerja, formulir, struktur organisasi">
        <a href="{{ route('documents.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('create', App\Models\Document::class)<a href="{{ route('documents.create') }}" class="btn-primary">+ Dokumen baru</a>@endcan
    </x-page-header>

    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / judul / ringkasan" class="input lg:col-span-2">
        <select name="type" class="input"><option value="">Semua jenis</option>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->code }} – {{ $t->name }}</option>@endforeach</select>
        <select name="division" class="input"><option value="">Semua divisi</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division') == $d->id)>{{ $d->code }} – {{ $d->name }}</option>@endforeach</select>
        <div class="flex gap-2">
            @if (auth()->user()->can('dokumen.lihat-semua') || auth()->user()->can('dokumen.kelola'))
            <select name="status" class="input">
                @foreach (['aktif' => 'Semua aktif', 'berlaku' => 'Ada revisi berlaku', 'proses' => 'Sedang diproses', 'arsip' => 'Diarsip'] as $k => $v)<option value="{{ $k }}" @selected(request('status', 'aktif') === $k)>{{ $v }}</option>@endforeach
            </select>
            @endif
            <button class="btn-secondary">Saring</button>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nomor</th><th>Judul</th><th class="hidden md:table-cell">Divisi</th><th>Revisi berlaku</th><th class="hidden sm:table-cell">Status</th><th class="hidden lg:table-cell">Tinjauan berikutnya</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($documents as $d)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('documents.show', $d) }}">{{ $d->number }}</a></td>
                    <td>{{ $d->title }}<div class="text-xs text-slate-500">{{ $d->type->name }}{{ $d->access_scope === 'umum' ? ' · Umum' : '' }}</div></td>
                    <td class="hidden md:table-cell">{{ $d->division->code }}</td>
                    <td class="whitespace-nowrap">{{ $d->currentRevision?->label() ?? '–' }}<div class="text-xs text-slate-500">{{ Format::date($d->currentRevision?->effective_date) }}</div></td>
                    <td class="hidden sm:table-cell">
                        @if ($d->isArchived()) <x-badge color="red">Diarsip</x-badge>
                        @else
                            @if ($d->current_revision_id)<x-badge color="green">Berlaku</x-badge>@endif
                            @if ($d->openRevision)<x-rev-status :status="$d->openRevision->status" />@endif
                        @endif
                    </td>
                    <td class="hidden whitespace-nowrap lg:table-cell {{ $d->next_review_date?->isPast() ? 'font-semibold text-red-600' : '' }}">{{ Format::date($d->next_review_date) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada dokumen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-app>
