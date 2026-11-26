@php use App\Support\Format; use App\Models\Audit; @endphp
<x-app :title="'Program Audit '.$p->year">
    <x-page-header :title="'Program Audit Internal '.$p->year" :subtitle="'Status: '.ucfirst($p->status)" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body space-y-3 lg:col-span-2">
            @include('partials.approved', ['m' => $p])
            @if ($p->status === 'draf' && auth()->user()->can('audit.kelola'))
                <form method="post" action="{{ route('audits.programs.update', $p) }}" class="space-y-3">@csrf @method('PUT')
                    <div><label class="label">Tujuan</label><textarea name="objective" rows="2" class="input">{{ $p->objective }}</textarea></div>
                    <div><label class="label">Ruang lingkup</label><textarea name="scope" rows="2" class="input">{{ $p->scope }}</textarea></div>
                    <div><label class="label">Kriteria</label><textarea name="criteria" rows="2" class="input">{{ $p->criteria }}</textarea></div>
                    <button class="btn-secondary btn-sm">Simpan</button>
                </form>
            @else
                <dl class="space-y-2 text-sm"><div><dt class="text-slate-500">Tujuan</dt><dd class="whitespace-pre-line">{{ $p->objective }}</dd></div><div><dt class="text-slate-500">Ruang lingkup</dt><dd class="whitespace-pre-line">{{ $p->scope }}</dd></div><div><dt class="text-slate-500">Kriteria</dt><dd class="whitespace-pre-line">{{ $p->criteria }}</dd></div></dl>
            @endif
        </section>
        <section class="space-y-3">
            @if ($p->status === 'draf' && auth()->user()->can('audit.kelola'))
                <form method="post" action="{{ route('audits.programs.submit', $p) }}" class="card card-body">@csrf<button class="btn-primary">Ajukan untuk pengesahan</button><p class="help">Disahkan oleh pengesah "Program audit tahunan" di Matriks Pengesahan (bawaan: Direktur).</p></form>
            @elseif ($p->status === 'pengesahan' && $canApprove)
                @include('partials.decide', ['action' => route('audits.programs.decide', $p), 'title' => 'Pengesahan program audit'])
            @endif
        </section>
    </div>
    <section class="card mt-5 overflow-x-auto">
        <h2 class="border-b px-4 py-3 font-semibold">Jadwal audit</h2>
        <table class="table">
            <thead><tr><th>No.</th><th>Proses</th><th>Divisi</th><th>Rencana</th><th>Auditor</th><th>Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($p->audits as $a)
                <tr><td class="whitespace-nowrap"><a class="link" href="{{ route('audits.show', $a) }}">{{ $a->number }}</a></td><td>{{ $a->process }}</td><td>{{ $a->division->code }}</td><td class="whitespace-nowrap">{{ Format::date($a->planned_date) }}</td><td>{{ $a->auditor->name }}</td><td><x-badge :color="$a->statusColor()">{{ Audit::STATUSES[$a->status] }}</x-badge></td></tr>
            @empty <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada audit terjadwal.</td></tr> @endforelse
            </tbody>
        </table>
    </section>
    @can('audit.kelola')
    <form method="post" action="{{ route('audits.schedule', $p) }}" class="card card-body mt-5 max-w-4xl space-y-3">@csrf
        <h2 class="font-semibold">Jadwalkan audit</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2"><label class="label">Proses yang diaudit *</label><input name="process" required class="input" placeholder="Mis. Penyelenggaraan pelatihan"></div>
            <div><label class="label">Divisi *</label><select name="division_id" required class="input">@foreach ($divisions as $d)<option value="{{ $d->id }}">{{ $d->code }} – {{ $d->name }}</option>@endforeach</select></div>
            <div><label class="label">Tanggal *</label><input type="date" name="planned_date" required class="input"><x-field-error name="planned_date" /></div>
            <div><label class="label">Auditor *</label><select name="auditor_id" required class="input"><option value="">Pilih…</option>@foreach ($auditors as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->division?->code ?? 'eksternal' }})</option>@endforeach</select><x-field-error name="auditor_id" /></div>
            <div><label class="label">Auditi</label><select name="auditee_id" class="input"><option value="">Kepala divisi (otomatis)</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        </div>
        <div><label class="label">Ruang lingkup</label><input name="scope" class="input"></div>
        <p class="help">Auditor tidak boleh mengaudit divisinya sendiri. Checklist bawaan (10 butir ISO 9001) dibuat otomatis dan bisa ditambah.</p>
        <button class="btn-primary w-fit">Jadwalkan</button>
    </form>
    @endcan
</x-app>
