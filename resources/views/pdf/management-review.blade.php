@php use App\Support\Format; use App\Models\ManagementReview; $docNumber = $m->number; @endphp
@extends('pdf.layout')
@section('content')
<h1>NOTULEN TINJAUAN MANAJEMEN</h1>
<table>
    <tr><th style="width:25%">Nomor</th><td>{{ $m->number }}</td></tr>
    <tr><th>Tanggal / tempat</th><td>{{ Format::date($m->meeting_date) }}{{ $m->location ? ' – '.$m->location : '' }}</td></tr>
    <tr><th>Periode ditinjau</th><td>{{ Format::date($m->period_from) }} s.d. {{ Format::date($m->period_to) }}</td></tr>
    <tr><th>Peserta</th><td class="pre">{{ $m->attendees }}</td></tr>
</table>
<h2>Masukan tinjauan (ISO 9001 9.3.2)</h2>
<table>@foreach (ManagementReview::INPUTS as $k => $label)<tr><th style="width:32%">{{ $label }}</th><td class="pre">{{ ($m->inputs[$k] ?? '') ?: '–' }}</td></tr>@endforeach</table>
<h2>Keluaran tinjauan (ISO 9001 9.3.3)</h2>
<table>@foreach (ManagementReview::OUTPUTS as $k => $label)<tr><th style="width:32%">{{ $label }}</th><td class="pre">{{ ($m->outputs[$k] ?? '') ?: '–' }}</td></tr>@endforeach</table>
<h2>Tindak lanjut</h2>
<table>
    <tr><th>Tindakan</th><th style="width:22%">PIC</th><th style="width:16%">Tenggat</th><th style="width:14%">Status</th></tr>
    @forelse ($m->actions as $a)<tr><td class="pre">{{ $a->description }}</td><td>{{ $a->pic->name }}</td><td>{{ Format::date($a->due_date) }}</td><td>{{ ucfirst($a->status) }}</td></tr>
    @empty <tr><td colspan="4">–</td></tr> @endforelse
</table>
@include('pdf._approval', ['m' => $m])
@endsection
