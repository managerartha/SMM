@php use App\Support\Format; @endphp
<x-app title="Kelas">
    <x-page-header title="Kelas" subtitle="Data kelas dari LMS (sumber utama). SMM hanya membaca dan menyimpan rekap per kelas.">
        <a href="{{ route('classes.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
        @can('lms.impor')<a href="{{ route('classes.import') }}" class="btn-primary">Impor dari LMS</a>@endcan
    </x-page-header>
    @if ($lastImport)<p class="mb-3 text-xs text-slate-500">Impor kelas terakhir: {{ Format::dateTime($lastImport->created_at) }} ({{ $lastImport->file_name }})</p>@endif
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Kode kelas / program / klien / trainer" class="input lg:col-span-2">
        <select name="program" class="input"><option value="">Semua program</option>@foreach ($programs as $p)<option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->code }} – {{ $p->name }}</option>@endforeach</select>
        <div class="flex gap-1 lg:col-span-2"><input type="date" name="from" value="{{ request('from') }}" class="input" title="Mulai dari"><input type="date" name="to" value="{{ request('to') }}" class="input" title="Sampai"><button class="btn-secondary">Saring</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Kode kelas</th><th>Program</th><th class="hidden md:table-cell">Tanggal</th><th class="hidden lg:table-cell">Trainer</th><th>Peserta</th><th class="hidden sm:table-cell">Hadir / Lulus</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $c)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap font-medium"><a class="link" href="{{ route('classes.show', $c) }}">{{ $c->class_code }}</a><div class="text-xs font-normal text-slate-500">{{ $c->scheme }}</div></td>
                    <td>{{ $c->program_name }}@if ($c->client)<div class="text-xs text-slate-500">Inhouse: {{ $c->client }}</div>@endif</td>
                    <td class="hidden whitespace-nowrap md:table-cell">{{ Format::date($c->start_date) }}<div class="text-xs text-slate-500">s.d. {{ Format::date($c->end_date) }}</div></td>
                    <td class="hidden lg:table-cell">{{ $c->trainers ?? '–' }}</td>
                    <td>{{ $c->participants ?? '–' }}</td>
                    <td class="hidden whitespace-nowrap sm:table-cell">{{ $c->attendanceRate() !== null ? $c->attendanceRate().'%' : '–' }} / {{ $c->passRate() !== null ? $c->passRate().'%' : '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada data kelas. @can('lms.impor')<a class="link" href="{{ route('classes.import') }}">Impor dari LMS</a>.@endcan</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-app>
