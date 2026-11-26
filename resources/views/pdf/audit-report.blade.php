@php use App\Support\Format; use App\Models\AuditChecklistItem; use App\Models\AuditFinding; $docNumber = $a->number; @endphp
@extends('pdf.layout')
@section('content')
<h1>LAPORAN AUDIT INTERNAL {{ $a->number }}</h1>
<table>
    <tr><th style="width:25%">Proses / divisi</th><td>{{ $a->process }} – {{ $a->division->name }}</td></tr>
    <tr><th>Tanggal audit</th><td>{{ Format::date($a->executed_at) }} (rencana {{ Format::date($a->planned_date) }})</td></tr>
    <tr><th>Auditor / auditi</th><td>{{ $a->auditor->name }} / {{ $a->auditee?->name ?? '–' }}</td></tr>
    <tr><th>Ruang lingkup</th><td class="pre">{{ $a->scope ?? '–' }}</td></tr>
    <tr><th>Kriteria</th><td class="pre">{{ $a->criteria }}</td></tr>
</table>
<h2>Checklist</h2>
<table>
    <tr><th style="width:8%">Klausul</th><th>Pertanyaan</th><th style="width:14%">Hasil</th><th>Bukti objektif</th></tr>
    @foreach ($a->items as $i)<tr><td>{{ $i->clause }}</td><td>{{ $i->question }}</td><td>{{ AuditChecklistItem::RESULTS[$i->result] ?? '–' }}</td><td class="pre">{{ $i->evidence }}</td></tr>@endforeach
</table>
<h2>Temuan</h2>
<table>
    <tr><th style="width:20%">Jenis</th><th style="width:10%">Klausul</th><th>Uraian</th><th style="width:16%">NCR</th></tr>
    @forelse ($a->findings as $f)<tr><td>{{ AuditFinding::TYPES[$f->type] }}</td><td>{{ $f->clause }}</td><td class="pre">{{ $f->description }}</td><td>{{ $f->ncr?->number ?? '–' }}</td></tr>
    @empty <tr><td colspan="4">Tidak ada temuan.</td></tr> @endforelse
</table>
<h2>Kesimpulan</h2>
<p class="pre">{{ $a->conclusion }}</p>
@include('pdf._approval', ['m' => $a])
@endsection
