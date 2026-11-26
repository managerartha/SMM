<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $docTitle ?? 'SMM' }}</title>
<style>
    @page { margin: 22mm 16mm 18mm 16mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; line-height: 1.35; }
    header { position: fixed; top: -15mm; left: 0; right: 0; font-size: 8pt; color: #555; border-bottom: 1px solid #999; padding-bottom: 2mm; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #777; border-top: 1px solid #ccc; padding-top: 1.5mm; }
    h1 { font-size: 14pt; margin: 0 0 2mm; } h2 { font-size: 11pt; margin: 5mm 0 2mm; border-bottom: 1px solid #999; padding-bottom: 1mm; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; } td, th { border: 1px solid #999; padding: 1.5mm 2mm; vertical-align: top; text-align: left; }
    th { background: #e5e7eb; font-weight: bold; } .muted { color: #666; font-size: 8pt; } .pre { white-space: pre-line; }
    .ok { color: #047857; font-weight: bold; } .bad { color: #b91c1c; font-weight: bold; } .right { text-align: right; }
    .sign { margin-top: 8mm; width: 60%; } .sign td { border: none; padding: 1mm 0; }
</style></head>
<body>
<header>PT Artha Safety Indonesia · Sistem Manajemen Mutu · {{ $docNumber ?? '' }}</header>
<footer>Dokumen dihasilkan oleh SMM pada {{ \App\Support\Format::dateTime(now()) }}. Pengesahan tercatat elektronik beserta jejak waktunya di log aktivitas.</footer>
@yield('content')
</body></html>
