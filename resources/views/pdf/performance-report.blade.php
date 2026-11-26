@php $d = $r->data; $docNumber = $r->number; @endphp
@extends('pdf.layout')
@section('content')
<h1>LAPORAN KINERJA LAYANAN</h1>
<p><b>{{ $r->number }}</b> · Periode {{ $r->periodLabel() }} ({{ \App\Support\Format::date($d['period']['from']) }} s.d. {{ \App\Support\Format::date($d['period']['to']) }})</p>
<h2>1. Pencapaian sasaran mutu (target vs realisasi)</h2>
@include('pdf._kpi-table', ['kpis' => $d['kpis']])
<h2>2. Pelaksanaan pelatihan</h2>
<table>
    <tr><th>Kelas selesai</th><th>Peserta</th><th>Kehadiran penuh</th><th>Kelulusan</th></tr>
    <tr><td>{{ $d['classes']['count'] }}</td><td>{{ $d['classes']['participants'] }}</td><td>{{ $d['classes']['attendance_pct'] ?? '–' }}%</td><td>{{ $d['classes']['pass_pct'] ?? '–' }}%</td></tr>
</table>
@if ($d['classes']['by_program'])<p class="muted">Per program: @foreach ($d['classes']['by_program'] as $p => $n){{ $p }} ({{ $n }}){{ $loop->last ? '' : ', ' }}@endforeach</p>@endif
<h2>3. Kepuasan pengguna layanan</h2>
<table>
    <tr><th>Survei</th><th>Respons</th><th>Rata-rata (1–5)</th><th>% puas</th><th>Negatif</th></tr>
    @foreach ($d['surveys'] as $s)<tr><td>{{ $s['label'] }}</td><td>{{ $s['count'] }}</td><td>{{ $s['average'] ?? '–' }}</td><td>{{ $s['satisfied_pct'] ?? '–' }}</td><td>{{ $s['negative'] }}</td></tr>@endforeach
</table>
<h2>4. Keluhan, ketidaksesuaian & tindakan korektif</h2>
<table>
    <tr><th>Keluhan</th><th>Feedback</th><th>Saran</th><th>Tanggapan tepat waktu</th><th>NCR baru</th><th>NCR ditutup</th><th>NCR terbuka</th><th>Lewat target</th></tr>
    <tr><td>{{ $d['complaints']['keluhan'] }}</td><td>{{ $d['complaints']['feedback'] }}</td><td>{{ $d['complaints']['saran'] }}</td><td>{{ $d['complaints']['ontime_pct'] ?? '–' }}%</td>
        <td>{{ $d['ncr']['new'] }}</td><td>{{ $d['ncr']['closed'] }}</td><td>{{ $d['ncr']['open_total'] }}</td><td>{{ $d['ncr']['overdue'] }}</td></tr>
</table>
<p class="muted">Pemantauan efektivitas: {{ $d['ncr']['effective'] }} efektif dari {{ $d['ncr']['effectiveness_checks'] }} pemeriksaan.</p>
<h2>5. Sumber daya & risiko</h2>
<p>Risiko/peluang aktif {{ $d['risks']['active'] }} (tinggi {{ $d['risks']['high'] }}). Berkas personel ≤ 90 hari berakhir: {{ $d['resources']['personnel_docs_expiring'] }}. Sarana rusak: {{ $d['resources']['facilities_bad'] }}. Audit selesai: {{ $d['audits']['done'] }}.</p>
<h2>6. Analisis & tindak lanjut</h2>
<p class="pre">{{ $r->analysis }}</p>
@include('pdf._approval', ['m' => $r])
@endsection
