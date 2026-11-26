@php use App\Support\Format; use App\Models\ManagementReview; $edit = $m->status === 'draf' && auth()->user()->can('tinjauan.kelola'); @endphp
<x-app :title="$m->number">
    <x-page-header :title="'Tinjauan Manajemen '.$m->number" :subtitle="Format::date($m->meeting_date).' · periode '.Format::date($m->period_from).' – '.Format::date($m->period_to)">
        <x-badge :color="['draf' => 'slate', 'pengesahan' => 'purple', 'disahkan' => 'green'][$m->status] ?? 'slate'">{{ ucfirst($m->status) }}</x-badge>
    </x-page-header>
    <div class="mb-4">@include('partials.approved', ['m' => $m])</div>
    @if ($canApprove)<div class="mb-5 max-w-xl">@include('partials.decide', ['action' => route('reviews.decide', $m), 'title' => 'Pengesahan notulen tinjauan manajemen'])</div>@endif

    <form method="post" action="{{ route('reviews.update', $m) }}" class="space-y-5">@csrf @method('PUT')
        <section class="card card-body grid gap-3 sm:grid-cols-2">
            <div><label class="label">Tempat</label>@if ($edit)<input name="location" value="{{ $m->location }}" class="input">@else<p class="text-sm">{{ $m->location ?: '–' }}</p>@endif</div>
            <div><label class="label">Peserta</label>@if ($edit)<textarea name="attendees" rows="2" class="input">{{ $m->attendees }}</textarea>@else<p class="whitespace-pre-line text-sm">{{ $m->attendees ?: '–' }}</p>@endif</div>
        </section>
        <section class="card">
            <div class="flex items-center justify-between border-b px-4 py-3"><h2 class="font-semibold">Masukan (9.3.2)</h2>
                @if ($edit)<button form="refresh-form" class="btn-secondary btn-sm">Isi ulang dari data terbaru</button>@endif</div>
            <div class="divide-y">
                @foreach (ManagementReview::INPUTS as $k => $label)
                    <div class="p-4"><label class="label">{{ $label }}</label>
                        @if ($edit)<textarea name="inputs[{{ $k }}]" rows="3" class="input">{{ $m->inputs[$k] ?? '' }}</textarea>
                        @else<p class="whitespace-pre-line text-sm">{{ $m->inputs[$k] ?? '–' }}</p>@endif</div>
                @endforeach
            </div>
        </section>
        <section class="card">
            <h2 class="border-b px-4 py-3 font-semibold">Keluaran (9.3.3)</h2>
            <div class="divide-y">
                @foreach (ManagementReview::OUTPUTS as $k => $label)
                    <div class="p-4"><label class="label">{{ $label }}{{ $k === 'kesimpulan' ? ' *' : '' }}</label>
                        @if ($edit)<textarea name="outputs[{{ $k }}]" rows="3" class="input">{{ $m->outputs[$k] ?? '' }}</textarea>
                        @else<p class="whitespace-pre-line text-sm">{{ $m->outputs[$k] ?? '–' }}</p>@endif</div>
                @endforeach
            </div>
        </section>
        @if ($edit)<button class="btn-primary">Simpan notulen</button>@endif
    </form>
    @if ($edit)<form id="refresh-form" method="post" action="{{ route('reviews.refresh', $m) }}" onsubmit="return confirm('Masukan akan ditimpa dengan data terbaru. Lanjutkan?')">@csrf</form>@endif

    <section class="card mt-5 overflow-x-auto">
        <h2 class="border-b px-4 py-3 font-semibold">Tindak lanjut</h2>
        <table class="table">
            <thead><tr><th>Tindakan</th><th>PIC</th><th>Batas</th><th>Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($m->actions as $act)
                <tr><td class="min-w-[16rem] whitespace-pre-line">{{ $act->description }}@if ($act->result)<p class="mt-1 text-xs text-slate-500">Hasil: {{ $act->result }}</p>@endif</td>
                    <td>{{ $act->pic?->name }}</td><td class="whitespace-nowrap">{{ Format::date($act->due_date) }}</td>
                    <td>@if ($act->completed_at)<x-badge color="green">Selesai {{ Format::date($act->completed_at) }}</x-badge>
                        @elseif (auth()->id() === $act->pic_id || auth()->user()->can('tinjauan.kelola'))
                            <form method="post" action="{{ route('reviews.actions.complete', [$m, $act]) }}" class="flex gap-1">@csrf<input name="result" required class="input min-w-[10rem]" placeholder="Hasil"><button class="btn-success btn-sm">Selesai</button></form>
                        @else<x-badge :color="$act->due_date->isPast() ? 'red' : 'amber'">{{ $act->due_date->isPast() ? 'Lewat batas' : 'Berjalan' }}</x-badge>@endif</td></tr>
            @empty <tr><td colspan="4" class="py-6 text-center text-slate-500">Belum ada tindak lanjut.</td></tr> @endforelse
            </tbody>
        </table>
        @if ($m->status !== 'disahkan' && auth()->user()->can('tinjauan.kelola'))
        <form method="post" action="{{ route('reviews.actions.store', $m) }}" class="grid gap-2 border-t p-4 sm:grid-cols-6">@csrf
            <input name="description" required class="input sm:col-span-3" placeholder="Tindakan">
            <select name="pic_id" required class="input sm:col-span-1"><option value="">PIC…</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            <input type="date" name="due_date" required class="input sm:col-span-1">
            <button class="btn-secondary sm:col-span-1">+ Tambah</button>
        </form>
        @endif
    </section>
    @if ($edit)
        <form method="post" action="{{ route('reviews.submit', $m) }}" class="mt-5">@csrf<button class="btn-primary">Ajukan notulen untuk pengesahan</button><p class="help">Simpan notulen terlebih dahulu. Kesimpulan wajib diisi.</p></form>
    @endif
</x-app>
