@php $docNumber = 'Rekap Survei'; @endphp
@extends('pdf.layout')
@section('content')
<h1>REKAP HASIL SURVEI PENGGUNA LAYANAN K3</h1>
<p>Periode {{ \App\Support\Format::date($from) }} s.d. {{ \App\Support\Format::date($to) }}. Sumber: LMS (diimpor ke SMM, tanpa data pribadi responden).</p>
@foreach ($summary as $s)
    <h2>{{ $s['label'] }}</h2>
    <p>{{ $s['count'] }} respons · rata-rata <b>{{ $s['average'] ?? '–' }}</b> / 5 · {{ $s['satisfied_pct'] ?? '–' }}% puas (skor ≥ 4) · {{ $s['negative'] }} negatif.</p>
    @if ($s['aspects'])<table><tr><th>Aspek</th><th class="right">Rata-rata</th></tr>@foreach ($s['aspects'] as $k => $v)<tr><td>{{ ucfirst(str_replace('_', ' ', $k)) }}</td><td class="right">{{ $v }}</td></tr>@endforeach</table>@endif
@endforeach
@endsection
