@php use App\Support\Format; use App\Models\SurveyResponse; @endphp
<x-app :title="$class->class_code">
    <x-page-header :title="$class->class_code.' – '.$class->program_name" :subtitle="($class->scheme ? $class->scheme.' · ' : '').Format::date($class->start_date).' s.d. '.Format::date($class->end_date)">
        @can('catatan.catat')<a href="{{ route('records.create', ['class' => $class->id]) }}" class="btn-primary">+ Catatan mutu kelas ini</a>@endcan
    </x-page-header>
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ([['Peserta', $class->participants ?? '–'], ['Hadir penuh', $class->attended !== null ? $class->attended.' ('.$class->attendanceRate().'%)' : '–'], ['Lulus', $class->passed !== null ? $class->passed.' ('.$class->passRate().'%)' : '–'], ['Rata-rata nilai', $class->average_score ?? '–'], ['Catatan mutu', $records->count()]] as [$l, $v])
            <div class="card card-body"><p class="text-xs text-slate-500">{{ $l }}</p><p class="mt-1 text-xl font-semibold">{{ $v }}</p></div>
        @endforeach
    </div>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <h2 class="border-b px-4 py-3 font-semibold">Catatan mutu (bukti pelaksanaan)</h2>
            <ul class="divide-y text-sm">
                @forelse ($records as $r)
                    <li class="px-4 py-2.5"><a class="link font-medium" href="{{ route('records.show', $r) }}">{{ $r->number }}</a> – {{ $r->title }}
                        <span class="text-xs text-slate-500">· {{ Format::date($r->activity_date) }} · {{ $r->attachments->count() }} berkas · {{ $r->creator->name }}</span></li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Belum ada catatan mutu untuk kelas ini (mis. daftar hadir, laporan trainer, foto, berita acara).</li>
                @endforelse
            </ul>
        </section>
        <section class="card card-body text-sm">
            <h2 class="mb-2 font-semibold">Info dari LMS</h2>
            <dl class="space-y-1.5">
                <div><dt class="text-slate-500">Program induk</dt><dd>{{ $class->program?->name ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Klien</dt><dd>{{ $class->client ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Lokasi</dt><dd>{{ $class->location ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Trainer</dt><dd>{{ $class->trainers ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Status LMS</dt><dd>{{ $class->lms_status ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Sinkron terakhir</dt><dd>{{ Format::dateTime($class->synced_at) }} ({{ strtoupper($class->source) }})</dd></div>
            </dl>
        </section>
    </div>
    @if ($surveySummary)
    <section class="card mt-5">
        <h2 class="border-b px-4 py-3 font-semibold">Hasil survei kelas ini</h2>
        <div class="grid gap-3 p-4 md:grid-cols-3">
            @foreach ($surveySummary as $s)
                <div class="rounded-md bg-slate-50 p-3 text-sm"><p class="font-medium">{{ $s['label'] }}</p>
                    <p class="text-2xl font-semibold">{{ $s['average'] ?? '–' }}<span class="text-sm font-normal text-slate-500"> / 5 · {{ $s['count'] }} respons</span></p>
                    @if ($s['negative'])<p class="text-xs text-red-600">{{ $s['negative'] }} respons negatif</p>@endif</div>
            @endforeach
        </div>
        @if ($responses->whereNotNull('comment')->isNotEmpty())
        <ul class="divide-y border-t text-sm">
            @foreach ($responses->whereNotNull('comment') as $r)
                <li class="flex items-start justify-between gap-2 px-4 py-2 {{ $r->is_negative ? 'bg-red-50' : '' }}"><span>“{{ $r->comment }}” <span class="text-xs text-slate-500">– {{ SurveyResponse::TYPES[$r->survey_type] }}, skor {{ $r->score }}</span></span>
                    @if ($r->is_negative && ! $r->complaint_id)@can('keluhan.catat')<form method="post" action="{{ route('surveys.complaint', $r) }}">@csrf<button class="btn-secondary btn-sm whitespace-nowrap">Jadikan keluhan</button></form>@endcan
                    @elseif ($r->complaint)<a class="link whitespace-nowrap text-xs" href="{{ route('complaints.show', $r->complaint) }}">{{ $r->complaint->number }}</a>@endif</li>
            @endforeach
        </ul>
        @endif
    </section>
    @endif
</x-app>
