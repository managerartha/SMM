@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' – ' : '' }}SMM Artha Safety</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
@php
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $navGroups = [
        null => [
            ['Dashboard', 'dashboard', 'dashboard', $u->can('dashboard.lihat')],
        ],
        'Dokumen' => [
            ['Dokumen Terkendali', 'documents.index', 'documents.*', $u->can('viewAny', App\Models\Document::class)],
            ['Catatan Mutu', 'records.index', 'records.*', $u->can('catatan.catat') || $u->can('catatan.lihat-semua')],
            ['Legalitas & Kontrak', 'external-documents.index', 'external-documents.*', $u->can('viewAny', App\Models\ExternalDocument::class)],
        ],
        'Pelaksanaan' => [
            ['Kelas (LMS)', 'classes.index', 'classes.*', $u->can('kelas.lihat')],
            ['Rekap Survei', 'surveys.index', 'surveys.*', $u->can('survei.lihat')],
            ['Personel', 'personnel.index', 'personnel.*', $u->can('personel.lihat')],
            ['Sarana & Prasarana', 'facilities.index', 'facilities.*', $u->can('sarana.lihat')],
            ['Pemasok', 'suppliers.index', 'suppliers.*', $u->can('pemasok.lihat')],
        ],
        'Perbaikan' => [
            ['Keluhan & Feedback', 'complaints.index', 'complaints.*', $u->can('viewAny', App\Models\Complaint::class)],
            ['NCR & CAPA', 'ncrs.index', 'ncrs.*', true],
            ['Risiko & Peluang', 'risks.index', 'risks.*', $u->can('viewAny', App\Models\Risk::class)],
        ],
        'Kinerja' => [
            ['Sasaran Mutu & KPI', 'kpis.index', 'kpis.*', $u->can('kpi.lihat')],
            ['Laporan Kinerja', 'reports.index', 'reports.*', $u->can('laporan.lihat')],
            ['Audit Internal', 'audits.index', 'audits.*', $u->can('audit.lihat') || $u->can('audit.laksana')],
            ['Tinjauan Manajemen', 'reviews.index', 'reviews.*', $u->can('tinjauan.lihat')],
            ['Program Inovasi', 'innovations.index', 'innovations.*', $u->can('inovasi.lihat')],
            ['Paket Bukti', 'evidence.index', 'evidence.*', $u->can('paket.unduh') || $u->can('paket.buat')],
            ['Log Aktivitas', 'activity.index', 'activity.*', $u->can('log.lihat')],
        ],
    ];
    $admin = array_filter([
        ['Pengguna', 'admin.users.index', 'admin.users.*', $u->can('pengguna.kelola')],
        ['Divisi', 'admin.divisions.index', 'admin.divisions.*', $u->can('master.kelola')],
        ['Jenis Dokumen', 'admin.document-types.index', 'admin.document-types.*', $u->can('master.kelola')],
        ['Kategori Eksternal', 'admin.external-categories.index', 'admin.external-categories.*', $u->can('master.kelola')],
        ['Program Pelatihan', 'admin.programs.index', 'admin.programs.*', $u->can('master.kelola')],
        ['Matriks Pengesahan', 'admin.approval-rules.index', 'admin.approval-rules.*', $u->can('pengaturan.kelola')],
        ['Peran & Izin', 'admin.roles.index', 'admin.roles.*', $u->can('pengaturan.kelola')],
        ['Pengaturan', 'admin.settings.index', 'admin.settings.*', $u->can('pengaturan.kelola')],
        ['Google Drive', 'admin.drive.index', 'admin.drive.*', $u->can('pengaturan.kelola')],
        ['Backup', 'admin.backup.index', 'admin.backup.*', $u->can('backup.kelola')],
        ['Pembaruan Sistem', 'admin.system.index', 'admin.system.*', $u->can('pengaturan.kelola')],
    ], fn ($i) => $i[3]);
@endphp
<div x-data="{ open: false }" class="min-h-full">
    {{-- Bilah atas --}}
    <header class="sticky top-0 z-30 bg-brand-800 text-white shadow">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-3 sm:px-4">
            <button type="button" class="rounded p-1.5 hover:bg-white/10 lg:hidden" @click="open = !open" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="grid h-8 w-8 place-items-center rounded bg-white p-0.5"><img src="{{ asset('logo.png') }}?v=2" alt="Logo Artha Safety Indonesia" class="h-full w-full object-contain"></span>
                <span class="hidden sm:block">SMM Artha Safety</span>
            </a>
            <form action="{{ route('search') }}" method="get" class="ml-auto hidden flex-1 sm:block sm:max-w-md">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor, judul, nama…" class="w-full rounded-md border-0 bg-white/15 px-3 py-1.5 text-sm text-white placeholder-white/70 focus:bg-white focus:text-slate-800 focus:ring-0">
            </form>
            <a href="{{ route('search') }}" class="ml-auto rounded p-1.5 hover:bg-white/10 sm:hidden" aria-label="Cari">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
            </a>
            <a href="{{ route('notifications.index') }}" class="relative rounded p-1.5 hover:bg-white/10" aria-label="Notifikasi">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0"/></svg>
                @if ($unread)<span class="absolute -right-0.5 -top-0.5 rounded-full bg-red-500 px-1.5 text-[10px] font-bold">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
            </a>
            <div x-data="{ m: false }" class="relative">
                <button @click="m = !m" class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-white/10">
                    <span class="hidden max-w-[10rem] truncate md:block">{{ $u->name }}</span>
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-white/20 text-xs font-semibold">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                </button>
                <div x-cloak x-show="m" @click.outside="m = false" class="absolute right-0 mt-1 w-56 rounded-md bg-white py-1 text-sm text-slate-700 shadow-lg ring-1 ring-slate-200">
                    <div class="border-b px-3 py-2 text-xs text-slate-500">{{ $u->roleLabels() }}</div>
                    <a href="{{ route('profile') }}" class="block px-3 py-2 hover:bg-slate-50">Profil & keamanan</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="block w-full px-3 py-2 text-left hover:bg-slate-50">Keluar</button></form>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl">
        {{-- Menu samping --}}
        <div x-cloak x-show="open" class="fixed inset-0 z-20 bg-black/30 lg:hidden" @click="open = false"></div>
        <nav :class="open ? 'translate-x-0!' : ''" class="fixed inset-y-0 left-0 z-20 w-64 -translate-x-full transform overflow-y-auto bg-white pt-14 shadow-lg transition lg:sticky lg:top-[var(--hdr,3.5rem)] lg:h-[calc(100vh-var(--hdr,3.5rem))] lg:shrink-0 lg:translate-x-0 lg:self-start lg:overscroll-contain lg:bg-transparent lg:pt-4 lg:pb-6 lg:shadow-none">
            @foreach ($navGroups as $group => $items)
                @php $items = array_filter($items, fn ($i) => $i[3]); @endphp
                @if ($items)
                    @if ($group)<p class="mt-4 px-6 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $group }}</p>@endif
                    <ul class="mt-1 space-y-0.5 px-3 text-sm">
                        @foreach ($items as [$label, $route, $pattern])
                            <li><a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif class="block rounded-md px-3 py-1.5 {{ request()->routeIs($pattern) ? 'bg-brand-100 font-semibold text-brand-800' : 'text-slate-700 hover:bg-slate-200/60' }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
            @if ($admin)
                <p class="mt-5 px-6 text-xs font-semibold uppercase tracking-wide text-slate-400">Admin</p>
                <ul class="mt-1 space-y-0.5 px-3 pb-6 text-sm">
                    @foreach ($admin as [$label, $route, $pattern])
                        <li><a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif class="block rounded-md px-3 py-1.5 {{ request()->routeIs($pattern) ? 'bg-brand-100 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-200/60' }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            @endif
        </nav>

        <main class="min-w-0 flex-1 px-3 py-4 sm:px-5 lg:py-6">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                    <p class="font-medium">Periksa kembali isian berikut:</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
