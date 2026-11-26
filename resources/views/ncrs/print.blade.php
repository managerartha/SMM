@php use App\Support\Format; use App\Models\Ncr; @endphp
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $ncr->number }}</title>
<style>
    body { font: 11pt/1.4 Arial, sans-serif; color: #111; margin: 24px; }
    h1 { font-size: 15pt; margin: 0; } h2 { font-size: 11.5pt; margin: 16px 0 6px; border-bottom: 1px solid #999; }
    table { width: 100%; border-collapse: collapse; } td, th { border: 1px solid #999; padding: 4px 6px; vertical-align: top; text-align: left; font-size: 10pt; }
    th { background: #eee; } .muted { color: #555; font-size: 9pt; } .pre { white-space: pre-line; }
    @media print { .noprint { display: none; } body { margin: 0; } }
</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Cetak / simpan PDF</button></p>
<table><tr><td style="width:65%"><h1>LAPORAN KETIDAKSESUAIAN & TINDAKAN KOREKTIF</h1><div class="muted">PT Artha Safety Indonesia · Sistem Manajemen Mutu</div></td>
<td><b>{{ $ncr->number }}</b><br>Status: {{ $ncr->statusLabel() }}<br><span class="muted">Dicetak {{ Format::dateTime(now()) }}</span></td></tr></table>

<h2>A. Ketidaksesuaian</h2>
<table>
<tr><th style="width:25%">Judul</th><td>{{ $ncr->title }}</td></tr>
<tr><th>Sumber</th><td>{{ Ncr::SOURCES[$ncr->source] ?? $ncr->source }}{{ $ncr->complaint ? ' ('.$ncr->complaint->number.')' : '' }}</td></tr>
<tr><th>Divisi / tingkat</th><td>{{ $ncr->division->name }} / {{ ucfirst($ncr->severity) }}</td></tr>
<tr><th>Ditemukan</th><td>{{ Format::date($ncr->found_at) }} – dicatat oleh {{ $ncr->creator->name }}</td></tr>
<tr><th>Uraian</th><td class="pre">{{ $ncr->description }}</td></tr>
<tr><th>Persyaratan</th><td>{{ $ncr->requirement ?? '–' }}</td></tr>
<tr><th>Penanggung jawab / target</th><td>{{ $ncr->pic->name }} / {{ Format::date($ncr->target_date) }}</td></tr>
</table>

<h2>B. Koreksi & analisis akar masalah</h2>
<table>
<tr><th style="width:25%">Koreksi</th><td class="pre">{{ $ncr->correction ?? '–' }}</td></tr>
<tr><th>Akar masalah ({{ Ncr::METHODS[$ncr->root_cause_method] ?? '–' }})</th><td class="pre">{{ $ncr->root_cause ?? '–' }}</td></tr>
</table>

<h2>C. Tindakan korektif</h2>
<table><tr><th>Siklus</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th><th>Pelaksanaan</th></tr>
@forelse ($ncr->actions as $a)
<tr><td>{{ $a->cycle }}</td><td class="pre">{{ $a->description }}</td><td>{{ $a->pic->name }}</td><td>{{ Format::date($a->due_date) }}</td><td class="pre">{{ $a->status === 'selesai' ? Format::date($a->completed_at).': '.$a->completion_note : $a->status }}</td></tr>
@empty <tr><td colspan="5">–</td></tr> @endforelse
</table>
<p>Verifikasi pelaksanaan: {{ $ncr->verified_at ? $ncr->verification_note.' – '.$ncr->verifier?->name.', '.Format::date($ncr->verified_at) : '–' }}</p>

<h2>D. Pemantauan efektivitas</h2>
<table><tr><th>Siklus</th><th>Hari ke-</th><th>Jadwal</th><th>Hasil</th><th>Catatan</th><th>Oleh</th></tr>
@forelse ($ncr->checks as $c)
<tr><td>{{ $c->cycle }}</td><td>{{ $c->day }}</td><td>{{ Format::date($c->due_date) }}</td><td>{{ $c->result ? ($c->result === 'efektif' ? 'Efektif' : 'Tidak efektif') : '–' }}</td><td class="pre">{{ $c->note }}</td><td>{{ $c->checker?->name }} {{ $c->checked_at ? Format::date($c->checked_at) : '' }}</td></tr>
@empty <tr><td colspan="6">–</td></tr> @endforelse
</table>

<h2>E. Penutupan</h2>
<p>{{ $ncr->closed_at ? 'Ditutup oleh '.$ncr->closed_by_name.($ncr->closed_by_position ? ' ('.$ncr->closed_by_position.')' : '').' pada '.Format::dateTime($ncr->closed_at).'. '.$ncr->closure_note : 'Belum ditutup.' }}</p>
<p class="muted">Dokumen ini dihasilkan dari SMM. Pengesahan tercatat secara elektronik beserta jejak waktunya (log aktivitas).</p>
</body></html>
