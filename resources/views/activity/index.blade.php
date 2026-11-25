@php use App\Support\Format; use App\Models\ActivityLog; @endphp
<x-app title="Log Aktivitas">
    <x-page-header title="Log Aktivitas" subtitle="Catatan permanen: tidak bisa diubah atau dihapus. Keutuhan diperiksa dengan rantai hash.">
        <a href="{{ route('activity.export', request()->query()) }}" class="btn-secondary">Ekspor Excel</a>
    </x-page-header>
    <form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Kata kunci" class="input lg:col-span-2">
        <select name="user_id" class="input"><option value="">Semua pengguna</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        <select name="action" class="input"><option value="">Semua aksi</option>@foreach (ActivityLog::ACTIONS as $k => $v)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>@endforeach</select>
        <div class="flex gap-1 lg:col-span-2"><input type="date" name="from" value="{{ request('from') }}" class="input"><input type="date" name="to" value="{{ request('to') }}" class="input"><button class="btn-secondary">Saring</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th class="hidden lg:table-cell">IP</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($logs as $l)
                <tr>
                    <td class="whitespace-nowrap text-xs">{{ Format::dateTime($l->occurred_at) }}</td>
                    <td class="whitespace-nowrap">{{ $l->user_name ?? 'Sistem' }}</td>
                    <td><x-badge :color="in_array($l->action, ['login_gagal', 'tolak']) ? 'red' : (in_array($l->action, ['setujui', 'verifikasi']) ? 'green' : 'slate')">{{ $l->actionLabel() }}</x-badge></td>
                    <td>{{ $l->description }}</td>
                    <td class="hidden whitespace-nowrap text-xs lg:table-cell">{{ $l->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">Tidak ada catatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app>
