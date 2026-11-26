@php use App\Support\Format; use App\Models\Audit; use App\Models\AuditChecklistItem; use App\Models\AuditFinding; @endphp
<x-app :title="$a->number">
    <x-page-header :title="$a->number.' – '.$a->process" :subtitle="$a->division->name.' · rencana '.Format::date($a->planned_date)">
        <a href="{{ route('audits.programs.show', $a->program) }}" class="btn-secondary">Program {{ $a->program->year }}</a>
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body text-sm lg:col-span-2 space-y-2">
            <div><x-badge :color="$a->statusColor()">{{ Audit::STATUSES[$a->status] }}</x-badge></div>
            <p><span class="text-slate-500">Auditor:</span> {{ $a->auditor->name }} · <span class="text-slate-500">Auditi:</span> {{ $a->auditee?->name ?? '–' }}</p>
            <p><span class="text-slate-500">Kriteria:</span> {{ $a->criteria }}</p>
            @if ($a->scope)<p><span class="text-slate-500">Ruang lingkup:</span> {{ $a->scope }}</p>@endif
            @include('partials.approved', ['m' => $a])
            @if ($a->status === 'dibatalkan')<p class="rounded bg-slate-100 p-2">Dibatalkan: {{ $a->cancel_reason }}</p>@endif
        </section>
        <section class="space-y-3">
            @if ($canApprove)@include('partials.decide', ['action' => route('audits.decide', $a), 'title' => 'Pengesahan laporan audit'])@endif
            @if (in_array($a->status, ['rencana', 'pelaksanaan']) && auth()->user()->can('audit.kelola'))
                <form method="post" action="{{ route('audits.cancel', $a) }}" class="card card-body space-y-2" x-data="{ o: false }">@csrf
                    <button type="button" class="btn-secondary btn-sm w-fit" x-show="!o" @click="o = true">Batalkan audit…</button>
                    <div x-cloak x-show="o" class="space-y-2"><textarea name="note" required class="input" rows="2" placeholder="Alasan"></textarea><button class="btn-danger btn-sm">Batalkan</button></div>
                </form>
            @endif
        </section>
    </div>

    <form method="post" action="{{ route('audits.checklist', $a) }}" class="card mt-5 overflow-x-auto">@csrf
        <h2 class="border-b px-4 py-3 font-semibold">Checklist</h2>
        <table class="table">
            <thead><tr><th>Klausul</th><th>Pertanyaan</th><th>Hasil</th><th>Bukti objektif</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($a->items as $it)
                <tr><td class="whitespace-nowrap">{{ $it->clause }}</td><td class="min-w-[16rem]">{{ $it->question }}</td>
                    @if ($canExecute)
                        <td><select name="items[{{ $it->id }}][result]" class="input min-w-[9rem]"><option value="">–</option>@foreach (AuditChecklistItem::RESULTS as $k => $v)<option value="{{ $k }}" @selected($it->result === $k)>{{ $v }}</option>@endforeach</select></td>
                        <td><textarea name="items[{{ $it->id }}][evidence]" rows="1" class="input min-w-[14rem]">{{ $it->evidence }}</textarea></td>
                    @else
                        <td>{{ AuditChecklistItem::RESULTS[$it->result] ?? '–' }}</td><td class="whitespace-pre-line">{{ $it->evidence }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
        @if ($canExecute)
            <div class="grid gap-2 border-t p-4 sm:grid-cols-6"><input name="new_clause" class="input sm:col-span-1" placeholder="Klausul"><input name="new_question" class="input sm:col-span-4" placeholder="Tambah pertanyaan (opsional)"><button class="btn-primary sm:col-span-1">Simpan</button></div>
        @endif
    </form>

    <section class="card mt-5">
        <h2 class="border-b px-4 py-3 font-semibold">Temuan</h2>
        <ul class="divide-y text-sm">
            @forelse ($a->findings as $f)
                <li class="px-4 py-2.5"><x-badge :color="['mayor' => 'red', 'minor' => 'amber', 'observasi' => 'blue', 'peluang' => 'green'][$f->type]">{{ AuditFinding::TYPES[$f->type] }}</x-badge> {{ $f->clause ? 'Klausul '.$f->clause.' · ' : '' }}
                    <span class="whitespace-pre-line">{{ $f->description }}</span>
                    @if ($f->ncr) → <a class="link" href="{{ route('ncrs.show', $f->ncr) }}">{{ $f->ncr->number }}</a> <x-badge :color="$f->ncr->statusColor()">{{ $f->ncr->statusLabel() }}</x-badge>@endif</li>
            @empty <li class="px-4 py-6 text-center text-slate-500">Belum ada temuan.</li> @endforelse
        </ul>
        @if ($canExecute)
        <form method="post" action="{{ route('audits.finding', $a) }}" class="grid gap-2 border-t p-4 sm:grid-cols-6">@csrf
            <select name="type" class="input sm:col-span-1">@foreach (AuditFinding::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input name="clause" class="input sm:col-span-1" placeholder="Klausul">
            <textarea name="description" rows="1" required class="input sm:col-span-3" placeholder="Uraian temuan & bukti"></textarea>
            <button class="btn-secondary sm:col-span-1">+ Temuan</button>
            <p class="help sm:col-span-6">Ketidaksesuaian mayor/minor otomatis dibuatkan NCR (PIC: auditi/kepala divisi; target 30/60 hari).</p>
        </form>
        @endif
    </section>

    <section class="card card-body mt-5 text-sm">
        <h2 class="mb-2 font-semibold">Kesimpulan & laporan</h2>
        @if ($a->conclusion)<p class="whitespace-pre-line">{{ $a->conclusion }}</p>@endif
        @include('partials.attachments', ['files' => $a->attachments->where('purpose', 'audit_evidence')])
        @if ($canExecute)
        <form method="post" action="{{ route('audits.report', $a) }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf
            <textarea name="conclusion" rows="3" required class="input" placeholder="Kesimpulan: tingkat kesesuaian & efektivitas proses, hal positif, area perbaikan">{{ old('conclusion', $a->conclusion) }}</textarea>
            <x-field-error name="conclusion" />
            <input type="file" name="attachments[]" multiple class="block text-sm">
            <button class="btn-primary">Ajukan laporan untuk pengesahan</button>
        </form>
        @endif
    </section>
</x-app>
