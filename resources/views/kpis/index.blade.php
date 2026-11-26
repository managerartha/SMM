<x-app title="Sasaran Mutu & KPI">
    <x-page-header title="Sasaran Mutu & KPI {{ $year }}" subtitle="Target, realisasi bulanan, tercapai (hijau) / tidak (merah)">
        <a href="{{ route('kpis.export', ['year' => $year]) }}" class="btn-secondary">Ekspor Excel</a>
        @can('kpi.kelola')<a href="{{ route('kpis.create') }}" class="btn-primary">+ KPI</a>@endcan
    </x-page-header>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get"><select name="year" onchange="this.form.submit()" class="input">@foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach</select></form>
        @can('kpi.kelola')
        <form method="post" action="{{ route('kpis.calculate') }}" class="flex items-center gap-2">@csrf
            <input type="month" name="period" value="{{ now()->format('Y-m') }}" class="input"><button class="btn-secondary">Hitung KPI otomatis</button></form>
        @endcan
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>KPI</th><th>Target</th>@foreach (['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $mn)<th class="text-center">{{ $mn }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($kpis as $k)
                <tr>
                    <td class="min-w-[14rem]"><a class="link font-medium" href="{{ route('kpis.show', $k) }}">{{ $k->code }}</a> {{ $k->name }}<div class="text-xs text-slate-500">{{ $k->division?->code }}{{ $k->source !== 'manual' ? ' · otomatis' : '' }}</div></td>
                    <td class="whitespace-nowrap text-xs">{{ $k->targetLabel() }}</td>
                    @for ($m = 1; $m <= 12; $m++)
                        @php $v = $k->values->firstWhere('period', sprintf('%d-%02d', $year, $m)); $ok = $k->achieved($v?->value); @endphp
                        <td class="text-center text-xs {{ $v ? ($ok ? 'bg-emerald-50 font-semibold text-emerald-800' : 'bg-red-50 font-semibold text-red-700') : 'text-slate-300' }}">{{ $v ? rtrim(rtrim(number_format($v->value, 2, ',', '.'), '0'), ',') : '·' }}</td>
                    @endfor
                </tr>
            @empty
                <tr><td colspan="14" class="py-10 text-center text-slate-500">Belum ada KPI untuk {{ $year }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($kpis->isNotEmpty())
    <div class="mt-5 grid gap-5 md:grid-cols-2">
        @foreach ($kpis as $k)
            <section class="card card-body"><p class="mb-1 text-sm font-semibold"><a class="link" href="{{ route('kpis.show', $k) }}">{{ $k->code }}</a> {{ $k->name }} <span class="font-normal text-slate-500">({{ $k->targetLabel() }})</span></p><x-kpi-chart :kpi="$k" /></section>
        @endforeach
    </div>
    @endif
</x-app>
