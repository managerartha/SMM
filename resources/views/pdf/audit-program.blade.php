@php use App\Support\Format; $docNumber = 'Program Audit '.$p->year; @endphp
@extends('pdf.layout')
@section('content')
<h1>PROGRAM AUDIT INTERNAL TAHUN {{ $p->year }}</h1>
<table>
    <tr><th style="width:25%">Tujuan</th><td class="pre">{{ $p->objective ?? '–' }}</td></tr>
    <tr><th>Ruang lingkup</th><td class="pre">{{ $p->scope ?? '–' }}</td></tr>
    <tr><th>Kriteria</th><td class="pre">{{ $p->criteria ?? '–' }}</td></tr>
</table>
<h2>Jadwal audit</h2>
<table>
    <tr><th>No. audit</th><th>Proses / divisi</th><th>Rencana</th><th>Auditor</th><th>Status</th></tr>
    @foreach ($p->audits as $a)
        <tr><td>{{ $a->number }}</td><td>{{ $a->process }}<br><span class="muted">{{ $a->division->name }}</span></td><td>{{ Format::date($a->planned_date) }}</td><td>{{ $a->auditor->name }}</td><td>{{ \App\Models\Audit::STATUSES[$a->status] }}</td></tr>
    @endforeach
</table>
@include('pdf._approval', ['m' => $p])
@endsection
