<x-app title="Peran & Izin">
    <x-page-header title="Peran & Izin" subtitle="Centang izin untuk tiap peran. GM (admin sistem) selalu memegang semua izin." />
    <form method="post" action="{{ route('admin.roles.update') }}" class="card overflow-x-auto">
        @csrf @method('PUT')
        <table class="table">
            <thead><tr><th class="min-w-[16rem]">Izin</th>@foreach ($roles as $r)<th class="text-center">{{ $labels[$r->name] ?? $r->name }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($permissions as $perm => $label)
                <tr>
                    <td>{{ $label }}<div class="text-xs text-slate-400">{{ $perm }}</div></td>
                    @foreach ($roles as $r)
                        <td class="text-center"><input type="checkbox" name="perm[{{ $r->name }}][{{ $perm }}]" value="1" @checked($r->permissions->contains('name', $perm)) @disabled($r->name === 'gm') class="rounded border-slate-300"></td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="border-t p-4"><button class="btn-primary">Simpan izin</button></div>
    </form>
</x-app>
