@php use App\Support\Format; $docNumber = 'Sistem Pengendalian Mutu Digital'; @endphp
@extends('pdf.layout')
@section('content')
<h1>SISTEM & LAPORAN PENGENDALIAN MUTU BERBASIS DIGITAL</h1>
<p>PT Artha Safety Indonesia mengelola sistem manajemen mutu (ISO 9001:2015 & persyaratan PJK3) melalui aplikasi web <b>SMM</b> di {{ config('app.url') }}.</p>
<h2>Modul</h2>
<table>
    <tr><th>Area</th><th>Modul</th></tr>
    <tr><td>Dokumen</td><td>Dokumen terkendali (penomoran otomatis, alur review & pengesahan, revisi, tanda air), catatan mutu bermasa simpan, legalitas & kontrak dengan pengingat masa berlaku</td></tr>
    <tr><td>Pelaksanaan</td><td>Kelas dari LMS, rekap survei, personel (TA/TT), sarana & prasarana, pemasok</td></tr>
    <tr><td>Perbaikan</td><td>Keluhan & feedback, NCR & CAPA dengan pemantauan efektivitas 30/60/90 hari, risiko & peluang</td></tr>
    <tr><td>Kinerja</td><td>Sasaran mutu & KPI (otomatis/manual), audit internal, tinjauan manajemen, program inovasi, laporan kinerja, paket bukti</td></tr>
</table>
<h2>Pengendalian</h2>
<table>
    <tr><th style="width:40%">Hak akses</th><td>Berbasis peran & izin; dicek di server untuk setiap aksi dan unduhan.</td></tr>
    <tr><th>Jejak audit</th><td>Log aktivitas append-only berantai hash: {{ number_format($stats['log_entries'], 0, ',', '.') }} entri pada periode; keutuhan rantai saat paket dibuat: <span class="{{ $stats['log_intact'] ? 'ok' : 'bad' }}">{{ $stats['log_intact'] ? 'UTUH' : 'TIDAK UTUH' }}</span>.</td></tr>
    <tr><th>Penyimpanan</th><td>Berkas di Google Drive perusahaan ({{ number_format($stats['files'], 0, ',', '.') }} berkas), tidak pernah ditimpa; unduhan hanya lewat aplikasi.</td></tr>
    <tr><th>Backup</th><td>Harian & bulanan, terenkripsi AES-256. Periode ini: @foreach ($stats['backups'] as $st => $n){{ $st }} {{ $n }}{{ $loop->last ? '' : ', ' }}@endforeach. Terakhir berhasil: {{ $stats['last_backup'] ? Format::dateTime($stats['last_backup']->started_at) : '–' }}.</td></tr>
    <tr><th>Dokumen berlaku</th><td>{{ $stats['documents'] }}</td></tr>
    <tr><th>Catatan mutu pada periode</th><td>{{ $stats['records'] }}</td></tr>
</table>
<p class="muted">Periode {{ Format::date($from) }} s.d. {{ Format::date($to) }}.</p>
@endsection
