<x-app title="Matriks Pengesahan">
    <x-page-header title="Matriks Pengesahan" subtitle="Siapa yang mengesahkan tiap jenis dokumen / laporan. Baris tahap 2–4 dipakai saat modulnya aktif." />
    <form method="post" action="{{ route('admin.approval-rules.update') }}" class="card overflow-x-auto">
        @csrf @method('PUT')
        <table class="table">
            <thead><tr><th>Jenis</th><th>Kunci</th><th>Tahap</th><th>Disahkan / diverifikasi oleh</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($rules as $r)
                <tr>
                    <td>{{ $r->label }} @if ($r->mode === 'verify')<x-badge color="amber">verifikasi keaslian</x-badge>@endif</td>
                    <td class="text-xs text-slate-500">{{ $r->subject_key }}</td>
                    <td>{{ $r->phase }}</td>
                    <td><select name="rules[{{ $r->id }}]" class="input max-w-xs">@foreach ($roles as $k => $v)<option value="{{ $k }}" @selected($r->approver_role === $k)>{{ $v }}</option>@endforeach</select></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="border-t p-4"><button class="btn-primary">Simpan matriks</button></div>
    </form>
</x-app>
