@php use App\Support\Format; use App\Models\Risk; use App\Domain\Risks\RiskLevel; @endphp
<x-app title="Risiko & Peluang">
    <x-page-header title="Risiko & Peluang" subtitle="Skor = Kemungkinan × Dampak (1–5). Level: 1–5 rendah, 6–11 sedang, 12–25 tinggi.">
        <a href="{{ route('risks.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('create', Risk::class)<a href="{{ route('risks.create') }}" class="btn-primary">+ Catat baru</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nomor / judul / proses" class="input lg:col-span-2">
        <select name="type" class="input"><option value="">Risiko & peluang</option>@foreach (Risk::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        <select name="level" class="input"><option value="">Semua level</option>@foreach (RiskLevel::cases() as $l)<option value="{{ $l->value }}" @selected(request('level') === $l->value)>{{ $l->label() }}</option>@endforeach</select>
        <select name="division" class="input"><option value="">Semua divisi</option>@foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division') == $d->id)>{{ $d->code }}</option>@endforeach</select>
        <div class="flex gap-2"><select name="status" class="input"><option value="aktif">Aktif</option><option value="ditutup" @selected(request('status') === 'ditutup')>Ditutup</option><option value="semua" @selected(request('status') === 'semua')>Semua</option></select><button class="btn-secondary">Saring</button></div>
    </form>

    <div class="mb-5 grid gap-5 lg:grid-cols-5">
        <section class="card card-body lg:col-span-2">
            <h2 class="mb-2 text-sm font-semibold">Peta panas (aktif)</h2>
            <div class="flex">
                <div class="mr-1 flex w-5 items-center justify-center"><span class="-rotate-90 whitespace-nowrap text-xs text-slate-500">Kemungkinan</span></div>
                <div class="flex-1">
                    <div class="grid grid-cols-6 gap-1 text-center text-xs">
                        @for ($l = 5; $l >= 1; $l--)
                            <div class="grid place-items-center text-slate-500">{{ $l }}</div>
                            @for ($i = 1; $i <= 5; $i++)
                                @php $lv = RiskLevel::fromScore($l * $i); $n = count($heat[$l][$i] ?? []);
                                     $bg = ['rendah' => 'bg-emerald-100', 'sedang' => 'bg-amber-100', 'tinggi' => 'bg-red-200'][$lv->value]; @endphp
                                <div class="{{ $bg }} grid aspect-square place-items-center rounded font-semibold {{ $n ? 'text-slate-900 ring-2 ring-slate-700' : 'text-slate-400' }}" title="{{ implode(', ', $heat[$l][$i] ?? []) }}">{{ $n ?: $l * $i }}</div>
                            @endfor
                        @endfor
                        <div></div>@for ($i = 1; $i <= 5; $i++)<div class="text-slate-500">{{ $i }}</div>@endfor
                    </div>
                    <p class="mt-1 text-center text-xs text-slate-500">Dampak</p>
                </div>
            </div>
            <p class="help">Angka tebal berbingkai = jumlah risiko/peluang di sel itu; angka pudar = skor sel.</p>
        </section>
        <section class="card overflow-x-auto lg:col-span-3">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Judul</th><th>Skor</th><th class="hidden md:table-cell">Pemilik</th><th class="hidden sm:table-cell">Tinjauan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($items as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('risks.show', $r) }}">{{ $r->number }}</a><div class="text-xs font-normal text-slate-500">{{ Risk::TYPES[$r->type] }} · {{ $r->division->code }}</div></td>
                        <td>{{ $r->title }}@if ($r->status === 'ditutup') <x-badge>Ditutup</x-badge>@endif<div class="text-xs text-slate-500">{{ $r->process }}</div></td>
                        <td class="whitespace-nowrap"><x-badge :color="$r->levelEnum()->color()">{{ $r->score }} · {{ $r->levelEnum()->label() }}</x-badge><div class="text-xs text-slate-500">K{{ $r->likelihood }} × D{{ $r->impact }}</div></td>
                        <td class="hidden md:table-cell">{{ $r->owner->name }}</td>
                        <td class="hidden whitespace-nowrap sm:table-cell {{ $r->isReviewDue() ? 'font-semibold text-amber-700' : '' }}">{{ Format::date($r->review_date) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-app>
