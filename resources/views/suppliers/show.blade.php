@php use App\Support\Format; use App\Models\Supplier; use App\Models\SupplierEvaluation; @endphp
<x-app :title="$s->number">
    <x-page-header :title="$s->number.' – '.$s->name" :subtitle="Supplier::CATEGORIES[$s->category] ?? $s->category">
        @can('pemasok.kelola')<a href="{{ route('suppliers.edit', $s) }}" class="btn-secondary">Ubah</a>@endcan
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body text-sm">
            <dl class="space-y-2">
                <div><dt class="text-slate-500">Status</dt><dd>{{ Supplier::STATUSES[$s->status] }}</dd></div>
                <div><dt class="text-slate-500">Kontak</dt><dd>{{ $s->contact_person ?? '–' }}<br>{{ $s->phone }} {{ $s->email }}</dd></div>
                @if ($s->address)<div><dt class="text-slate-500">Alamat</dt><dd class="whitespace-pre-line">{{ $s->address }}</dd></div>@endif
                @if ($s->notes)<div><dt class="text-slate-500">Catatan</dt><dd class="whitespace-pre-line">{{ $s->notes }}</dd></div>@endif
            </dl>
        </section>
        <section class="card overflow-x-auto lg:col-span-2">
            <h2 class="border-b px-4 py-3 font-semibold">Riwayat evaluasi (skala 1–5)</h2>
            <table class="table">
                <thead><tr><th>Periode</th><th>Mutu</th><th>Waktu</th><th>Harga</th><th>Layanan</th><th>Rata-rata</th><th>Keputusan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($s->evaluations as $e)
                    <tr><td class="whitespace-nowrap">{{ $e->period }}<div class="text-xs text-slate-500">{{ Format::date($e->evaluated_at) }}, {{ $e->evaluator->name }}</div></td><td>{{ $e->quality }}</td><td>{{ $e->timeliness }}</td><td>{{ $e->price }}</td><td>{{ $e->service }}</td>
                        <td class="font-semibold">{{ number_format($e->average, 2, ',', '.') }}</td><td>{{ SupplierEvaluation::DECISIONS[$e->decision] }}@if ($e->notes)<div class="text-xs text-slate-500">{{ $e->notes }}</div>@endif</td></tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum pernah dievaluasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
    @can('pemasok.kelola')
    <form method="post" action="{{ route('suppliers.evaluate', $s) }}" class="card card-body mt-5 max-w-4xl space-y-3">@csrf
        <h2 class="font-semibold">Evaluasi pemasok</h2>
        <div class="grid gap-3 sm:grid-cols-6">
            <div class="sm:col-span-2"><label class="label">Periode</label><input name="period" value="{{ now()->format('Y').'-S'.(now()->month <= 6 ? 1 : 2) }}" required class="input"></div>
            @foreach (['quality' => 'Mutu', 'timeliness' => 'Ketepatan waktu', 'price' => 'Harga', 'service' => 'Layanan'] as $k => $v)
                <div><label class="label">{{ $v }}</label><select name="{{ $k }}" class="input">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="label">Keputusan</label><select name="decision" class="input">@foreach (SupplierEvaluation::DECISIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="label">Catatan</label><input name="notes" class="input"></div>
        </div>
        <button class="btn-primary w-fit">Simpan evaluasi</button>
    </form>
    @endcan
</x-app>
