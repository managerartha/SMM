@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Masuk' }} – SMM Artha Safety</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 text-center">
        <img src="{{ asset('logo.png') }}?v=2" alt="Logo Artha Safety Indonesia" class="mx-auto h-16 w-16 object-contain">
        <h1 class="mt-3 text-lg font-semibold">Sistem Manajemen Mutu</h1>
        <p class="text-sm text-slate-500">PT Artha Safety Indonesia</p>
    </div>
    <div class="card card-body w-full max-w-sm">
        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
</body>
</html>
