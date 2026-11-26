@php use App\Support\Format; @endphp
<x-app :title="$r->number">
    <x-page-header :title="$r->number.' – '.$r->title" :subtitle="$r->division->name.' · '.Format::date($r->activity_date)" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card card-body lg:col-span-2 text-sm">
            @if ($r->archived_at)<p class="mb-3 rounded bg-slate-100 p-2">Diarsip {{ Format::date($r->archived_at) }}.</p>@endif
            @if ($r->description)<p class="mb-3 whitespace-pre-line">{{ $r->description }}</p>@endif
            <h2 class="font-semibold">Berkas ({{ $r->attachments->count() }})</h2>
            @include('partials.attachments', ['files' => $r->attachments])
            @can('catatan.catat')
            <form method="post" action="{{ route('records.files', $r) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-2">@csrf
                <input type="file" name="attachments[]" multiple required class="text-sm"><button class="btn-secondary btn-sm">Tambah berkas</button></form>
            @endcan
        </section>
        <section class="card card-body text-sm">
            <dl class="space-y-2">
                <div><dt class="text-slate-500">Formulir</dt><dd>@if ($r->form)<a class="link" href="{{ route('documents.show', $r->form) }}">{{ $r->form->number }}</a> {{ $r->form->title }}@else – @endif</dd></div>
                <div><dt class="text-slate-500">Kelas</dt><dd>@if ($r->trainingClass)<a class="link" href="{{ route('classes.show', $r->trainingClass) }}">{{ $r->trainingClass->class_code }}</a>@else – @endif</dd></div>
                <div><dt class="text-slate-500">Masa simpan</dt><dd class="{{ $r->isPastRetention() ? 'font-semibold text-amber-700' : '' }}">{{ $r->retention_years }} tahun, s.d. {{ Format::date($r->retention_until) }}</dd></div>
                <div><dt class="text-slate-500">Kategori bukti</dt><dd>{{ $r->evidenceCategory?->label() ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Dicatat oleh</dt><dd>{{ $r->creator->name }}, {{ Format::dateTime($r->created_at) }}</dd></div>
            </dl>
            @if (! $r->archived_at && auth()->user()->can('catatan.lihat-semua') && auth()->user()->can('catatan.catat'))
                <form method="post" action="{{ route('records.archive', $r) }}" class="mt-4" onsubmit="return confirm('Arsipkan catatan ini? Berkas tetap tersimpan.')">@csrf<button class="btn-secondary btn-sm">Arsipkan</button></form>
            @endif
        </section>
    </div>
</x-app>
