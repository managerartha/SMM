@php use App\Support\Format; use App\Models\Innovation; $u = auth()->user(); $isPic = in_array($u->id, [$i->pic_id, $i->proposer_id]) || $u->can('inovasi.kelola'); @endphp
<x-app :title="$i->number">
    <x-page-header :title="$i->number.' – '.$i->title" :subtitle="'Diusulkan '.$i->proposer->name.' · '.Format::date($i->created_at)">
        @if ($i->status === 'usulan' && ($u->id === $i->proposer_id || $u->can('inovasi.kelola')))<a href="{{ route('innovations.edit', $i) }}" class="btn-secondary">Ubah</a>@endif
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body space-y-3 text-sm lg:col-span-2">
            <div><x-badge :color="$i->statusColor()">{{ Innovation::STATUSES[$i->status] }}</x-badge> <span class="text-xs text-slate-500">Pengesah: {{ $approverRole }}</span></div>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-slate-500">Latar belakang</dt><dd class="whitespace-pre-line">{{ $i->background }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Tujuan</dt><dd class="whitespace-pre-line">{{ $i->objective }}</dd></div>
                @if ($i->plan)<div class="sm:col-span-2"><dt class="text-slate-500">Rencana</dt><dd class="whitespace-pre-line">{{ $i->plan }}</dd></div>@endif
                <div><dt class="text-slate-500">Indikator</dt><dd>{{ $i->indicator }}</dd></div>
                <div><dt class="text-slate-500">Kondisi awal</dt><dd>{{ $i->baseline ?: '–' }}</dd></div>
                <div><dt class="text-slate-500">Anggaran</dt><dd>{{ $i->budget ? 'Rp '.number_format($i->budget, 0, ',', '.') : 'Tanpa anggaran' }}</dd></div>
                <div><dt class="text-slate-500">PIC · Divisi</dt><dd>{{ $i->pic?->name ?? '–' }} · {{ $i->division?->code ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Jadwal</dt><dd>{{ Format::date($i->start_date) }} – {{ Format::date($i->end_date) }}</dd></div>
            </dl>
            @include('partials.approved', ['m' => $i])
            @if ($i->status === 'selesai')
                <div class="rounded bg-emerald-50 p-3"><p class="font-medium">Hasil: {{ $i->result_value }}</p><p class="whitespace-pre-line">{{ $i->result }}</p><p class="mt-1 whitespace-pre-line text-slate-600">Evaluasi: {{ $i->evaluation }}</p></div>
            @endif
            <div><p class="text-slate-500">Bukti pelaksanaan</p>@include('partials.attachments', ['files' => $i->attachments])</div>
        </section>
        <section class="space-y-3">
            @if ($canDecide)@include('partials.decide', ['action' => route('innovations.decide', $i), 'title' => 'Persetujuan usulan', 'yes' => 'Setujui', 'yesValue' => 'setuju', 'no' => 'Tolak'])@endif
            @if ($i->status === 'disetujui' && $isPic)
                <form method="post" action="{{ route('innovations.start', $i) }}" class="card card-body">@csrf<button class="btn-primary">Mulai pelaksanaan</button></form>
            @endif
            @if ($i->status === 'pelaksanaan' && $isPic)
                <form method="post" action="{{ route('innovations.evidence', $i) }}" enctype="multipart/form-data" class="card card-body space-y-2">@csrf
                    <p class="text-sm font-medium">Tambah bukti</p><input type="file" name="evidence[]" multiple required class="block text-sm"><x-field-error name="evidence" /><button class="btn-secondary btn-sm w-fit">Unggah</button></form>
                <form method="post" action="{{ route('innovations.complete', $i) }}" class="card card-body space-y-2">@csrf
                    <p class="text-sm font-medium">Selesaikan & evaluasi</p>
                    <input name="result_value" required class="input" placeholder="Nilai hasil indikator (mis. 5 hari)">
                    <textarea name="result" required rows="2" class="input" placeholder="Uraian hasil"></textarea>
                    <textarea name="evaluation" required rows="2" class="input" placeholder="Evaluasi: tercapai? perlu distandarkan (SOP/IK)?"></textarea>
                    <x-field-error name="result" /><button class="btn-success">Tandai selesai</button>
                    <p class="help">Minimal satu bukti pelaksanaan harus diunggah.</p>
                </form>
            @endif
        </section>
    </div>
</x-app>
