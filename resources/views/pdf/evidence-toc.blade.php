@php use App\Support\Format; $docNumber = 'Paket Bukti'; @endphp
@extends('pdf.layout')
@section('content')
<h1>DAFTAR ISI PAKET BUKTI</h1>
<p>PT Artha Safety Indonesia · Periode catatan: <b>{{ Format::date($pkg->date_from) }} s.d. {{ Format::date($pkg->date_to) }}</b> · Dibuat {{ Format::dateTime(now()) }} oleh {{ $pkg->creator->name }}.</p>
<p class="muted">Berisi dokumen yang berlaku saat paket dibuat (dokumen terkendali bertanda "SALINAN TERKENDALI") serta catatan & rekaman dalam periode di atas.</p>
<table>
    <tr><th style="width:6%">No</th><th>Dokumen wajib</th><th style="width:30%">Folder</th><th style="width:10%" class="right">Berkas</th></tr>
    @foreach ($categories as $c)<tr><td>{{ $c->id }}</td><td>{{ $c->name }}</td><td>{{ $c->folder_name }}</td><td class="right {{ empty($index[$c->id]) ? 'bad' : '' }}">{{ count($index[$c->id] ?? []) }}</td></tr>@endforeach
</table>
@foreach ($categories as $c)
    <h2>{{ $c->folder_name }} – {{ $c->name }}</h2>
    @if (empty($index[$c->id]))<p class="bad">Belum ada berkas untuk kategori ini pada periode tersebut.</p>
    @else<table>@foreach ($index[$c->id] as $e)<tr><td style="width:55%">{{ $e['path'] }}</td><td class="muted">{{ $e['source'] }}</td></tr>@endforeach</table>@endif
@endforeach
@if ($warnings)<h2>Peringatan</h2><ul>@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>@endif
@endsection
