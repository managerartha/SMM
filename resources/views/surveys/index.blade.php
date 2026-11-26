@php use App\Support\Format; use App\Models\SurveyResponse; @endphp
<x-app title="Rekap Survei">
    <x-page-header title="Rekap Survei" subtitle="Dari LMS: kepuasan peserta, evaluasi trainer, evaluasi klien perusahaan (skala 1–5)">
        <a href="{{ route('surveys.export', request()->query() + ['from' => $from, 'to' => $to]) }}" class="btn-secondary">Ekspor Excel</a>
        @can('lms.impor')<a href="{{ route('classes.import') }}" class="btn-primary">Impor survei</a>@endcan
    </x-page-header>
    <form method="get" class="card card-body mb-4 flex flex-wrap items-end gap-2">
        <div><label class="label">Dari</label><input type="date" name="from" value="{{ $from }}" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" name="to" value="{{ $to }}" class="input"></div>
        <select name="type" class="input max-w-[14rem]"><option value="">Semua jenis</option>@foreach (SurveyResponse::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Kode kelas / komentar / trainer" class="input max-w-xs">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="negatif" value="1" @checked(request('negatif')) class="rounded border-slate-300"> Negatif saja</label>
        <button class="btn-secondary">Saring</button>
    </form>
    <div class="mb-5 grid gap-3 md:grid-cols-3">
        @foreach ($summary as $s)
            <section class="card card-body text-sm">
                <p class="font-semibold">{{ $s['label'] }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $s['average'] ?? '–' }}<span class="text-sm font-normal text-slate-500"> / 5</span></p>
                <p class="text-slate-600">{{ $s['count'] }} respons · {{ $s['satisfied_pct'] !== null ? $s['satisfied_pct'].'% puas (≥4)' : '' }}</p>
                @if ($s['negative'])<p class="text-red-600">{{ $s['negative'] }} respons negatif</p>@endif
                @if ($s['aspects'])
                    <ul class="mt-2 space-y-1">@foreach ($s['aspects'] as $k => $v)
                        <li class="flex items-center gap-2"><span class="w-24 truncate text-xs text-slate-600">{{ ucfirst(str_replace('_', ' ', $k)) }}</span><span class="h-2 flex-1 rounded bg-slate-100"><span class="block h-2 rounded {{ $v >= 4 ? 'bg-emerald-500' : ($v >= 3 ? 'bg-amber-400' : 'bg-red-500') }}" style="width: {{ $v / 5 * 100 }}%"></span></span><span class="w-8 text-right text-xs">{{ $v }}</span></li>
                    @endforeach</ul>
                @endif
            </section>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kelas</th><th>Skor</th><th>Komentar</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($responses as $r)
                <tr class="{{ $r->is_negative ? 'bg-red-50/60' : '' }}">
                    <td class="whitespace-nowrap">{{ Format::date($r->responded_at) }}</td>
                    <td class="text-xs">{{ SurveyResponse::TYPES[$r->survey_type] }}{{ $r->subject ? ' · '.$r->subject : '' }}</td>
                    <td class="whitespace-nowrap">@if ($r->trainingClass)<a class="link" href="{{ route('classes.show', $r->trainingClass) }}">{{ $r->class_code }}</a>@else {{ $r->class_code ?? '–' }} @endif</td>
                    <td class="font-semibold {{ $r->is_negative ? 'text-red-700' : '' }}">{{ $r->score }}</td>
                    <td>{{ $r->comment ?? '–' }}</td>
                    <td class="whitespace-nowrap">@if ($r->complaint)<a class="link text-xs" href="{{ route('complaints.show', $r->complaint) }}">{{ $r->complaint->number }}</a>@elseif ($r->is_negative)@can('keluhan.catat')<form method="post" action="{{ route('surveys.complaint', $r) }}">@csrf<button class="btn-secondary btn-sm">Jadikan keluhan</button></form>@endcan @endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada respons survei pada rentang ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $responses->links() }}</div>
</x-app>
