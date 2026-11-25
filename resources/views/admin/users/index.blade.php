@php use App\Support\Format; @endphp
<x-app title="Pengguna">
    <x-page-header title="Pengguna" subtitle="Akun tidak pernah dihapus — hanya dinonaktifkan.">
        <a href="{{ route('admin.users.export') }}" class="btn-secondary">Ekspor Excel</a>
        <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Pengguna</a>
    </x-page-header>
    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / email" class="input max-w-xs">
        <select name="status" class="input max-w-[10rem]"><option value="">Aktif</option><option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option></select>
        <button class="btn-secondary">Saring</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nama</th><th class="hidden md:table-cell">Divisi</th><th>Peran</th><th class="hidden lg:table-cell">Login terakhir</th><th class="hidden sm:table-cell">2FA</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($users as $u)
                <tr>
                    <td><a class="link font-medium" href="{{ route('admin.users.edit', $u) }}">{{ $u->name }}</a><div class="text-xs text-slate-500">{{ $u->email }}{{ $u->position ? ' · '.$u->position : '' }}</div>
                        @if ($u->access_expires_at)<div class="text-xs {{ $u->access_expires_at->isPast() ? 'text-red-600' : 'text-amber-700' }}">Akun berlaku s.d. {{ Format::date($u->access_expires_at) }}</div>@endif</td>
                    <td class="hidden md:table-cell">{{ $u->division?->code ?? '–' }}</td>
                    <td class="text-xs">{{ $u->roleLabels() }}</td>
                    <td class="hidden whitespace-nowrap text-xs lg:table-cell">{{ Format::dateTime($u->last_login_at) }}</td>
                    <td class="hidden sm:table-cell">{!! $u->two_factor_confirmed_at ? '<span class="badge bg-emerald-100 text-emerald-800">Aktif</span>' : '–' !!}</td>
                    <td class="whitespace-nowrap text-right">
                        @if ($u->id !== auth()->id())
                        <form method="post" action="{{ route('admin.users.toggle', $u) }}" onsubmit="return confirm('{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $u->name }}?')">@csrf
                            <button class="btn-secondary btn-sm">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app>
