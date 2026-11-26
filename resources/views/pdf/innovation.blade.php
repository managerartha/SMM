@php use App\Support\Format; $docNumber = $i->number; @endphp
@extends('pdf.layout')
@section('content')
<h1>PROGRAM INOVASI MUTU {{ $i->number }}</h1>
<table>
    <tr><th style="width:25%">Judul</th><td>{{ $i->title }}</td></tr>
    <tr><th>Pengusul / PIC</th><td>{{ $i->proposer->name }} / {{ $i->pic?->name ?? '–' }}</td></tr>
    <tr><th>Latar belakang</th><td class="pre">{{ $i->background }}</td></tr>
    <tr><th>Tujuan</th><td class="pre">{{ $i->objective }}</td></tr>
    <tr><th>Rencana</th><td class="pre">{{ $i->plan ?? '–' }}</td></tr>
    <tr><th>Indikator / kondisi awal</th><td>{{ $i->indicator ?? '–' }} / {{ $i->baseline ?? '–' }}</td></tr>
    <tr><th>Anggaran</th><td>{{ $i->budget ? 'Rp '.number_format($i->budget, 0, ',', '.') : 'Tanpa anggaran' }}</td></tr>
    <tr><th>Persetujuan</th><td>{{ \App\Models\Innovation::STATUSES[$i->status] }} – {{ $i->approved_by_name }}{{ $i->approved_by_position ? ' ('.$i->approved_by_position.')' : '' }}, {{ Format::date($i->approved_at) }}</td></tr>
    <tr><th>Pelaksanaan</th><td>{{ Format::date($i->start_date) }} s.d. {{ Format::date($i->end_date) }}</td></tr>
    <tr><th>Hasil</th><td class="pre">{{ $i->result ?? '–' }}{{ $i->result_value ? "\nNilai indikator setelah: ".$i->result_value : '' }}</td></tr>
    <tr><th>Evaluasi</th><td class="pre">{{ $i->evaluation ?? '–' }}</td></tr>
</table>
@endsection
