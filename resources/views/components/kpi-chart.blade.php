@props(['kpi', 'height' => 160])
@php
    // Grafik tren 12 bulan (SVG, tanpa pustaka eksternal): garis realisasi + garis target.
    $year = $kpi->year; $vals = [];
    for ($m = 1; $m <= 12; $m++) { $v = $kpi->values->firstWhere('period', sprintf('%d-%02d', $year, $m)); $vals[$m] = $v?->value; }
    $nums = array_filter($vals, fn ($v) => $v !== null); $all = array_merge($nums, [$kpi->target]);
    $max = max($all) ?: 1; $min = min(0, min($all)); $span = ($max - $min) ?: 1; $max += $span * 0.1; $span = $max - $min;
    $W = 600; $H = $height; $pl = 36; $pr = 10; $pt = 10; $pb = 22;
    $x = fn ($m) => $pl + ($m - 1) * ($W - $pl - $pr) / 11;
    $y = fn ($v) => $pt + ($H - $pt - $pb) * (1 - ($v - $min) / $span);
    $pts = []; foreach ($vals as $m => $v) { if ($v !== null) $pts[] = round($x($m), 1).','.round($y($v), 1); }
    $months = ['J','F','M','A','M','J','J','A','S','O','N','D'];
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full" role="img" aria-label="Tren {{ $kpi->name }}">
    @foreach ([0, 0.5, 1] as $f) @php $gy = $pt + ($H - $pt - $pb) * $f; $gv = $max - $span * $f; @endphp
        <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $gy }}" y2="{{ $gy }}" stroke="#e2e8f0" stroke-width="1"/>
        <text x="{{ $pl - 4 }}" y="{{ $gy + 4 }}" font-size="10" text-anchor="end" fill="#94a3b8">{{ round($gv, 1) }}</text>
    @endforeach
    <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $y($kpi->target) }}" y2="{{ $y($kpi->target) }}" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="5 4"/>
    <text x="{{ $W - $pr }}" y="{{ $y($kpi->target) - 4 }}" font-size="10" text-anchor="end" fill="#dc2626">target {{ $kpi->target }}</text>
    @if (count($pts) > 1)<polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="#1e40af" stroke-width="2"/>@endif
    @foreach ($vals as $m => $v)
        <text x="{{ $x($m) }}" y="{{ $H - 6 }}" font-size="10" text-anchor="middle" fill="#64748b">{{ $months[$m - 1] }}</text>
        @if ($v !== null)<circle cx="{{ $x($m) }}" cy="{{ $y($v) }}" r="4" fill="{{ $kpi->achieved($v) ? '#059669' : '#dc2626' }}"><title>{{ sprintf('%d-%02d', $year, $m) }}: {{ $v }} {{ $kpi->unit }}</title></circle>@endif
    @endforeach
</svg>
