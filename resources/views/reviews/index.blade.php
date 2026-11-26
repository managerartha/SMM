@php use App\Support\Format; @endphp
<x-app title="Tinjauan Manajemen">
    <x-page-header title="Tinjauan Manajemen" subtitle="ISO 9001:2015 klausul 9.3 – masukan terisi otomatis dari data SMM, keluaran & tindak lanjut dipantau" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal rapat</th><th>Periode</th><th>Tindak lanjut</th><th>Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($items as $m)
                    <tr><td class="whitespace-nowrap"><a class="link" href="{{ route('reviews.show', $m) }}">{{ $m->number }}</a></td><td class="whitespace-nowrap">{{ Format::date($m->meeting_date) }}</td>
                        <td class="whitespace-nowrap">{{ Format::date($m->period_from) }} – {{ Format::date($m->period_to) }}</td><td>{{ $m->actions_count }}</td>
                        <td><x-badge :color="['draf' => 'slate', 'pengesahan' => 'purple', 'disahkan' => 'green'][$m->status] ?? 'slate'">{{ ucfirst($m->status) }}</x-badge></td></tr>
                @empty <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada tinjauan manajemen.</td></tr> @endforelse
                </tbody>
            </table>
        </section>
        @can('tinjauan.kelola')
        <form method="post" action="{{ route('reviews.store') }}" class="card card-body space-y-3">@csrf
            <h2 class="font-semibold">Tinjauan baru</h2>
            <div><label class="label">Tanggal rapat *</label><input type="date" name="meeting_date" required value="{{ now()->toDateString() }}" class="input"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">Periode dari *</label><input type="date" name="period_from" required value="{{ now()->subYear()->startOfMonth()->toDateString() }}" class="input"></div>
                <div><label class="label">s.d. *</label><input type="date" name="period_to" required value="{{ now()->subMonth()->endOfMonth()->toDateString() }}" class="input"><x-field-error name="period_to" /></div>
            </div>
            <div><label class="label">Tempat</label><input name="location" class="input" value="Kantor PT Artha Safety Indonesia"></div>
            <div><label class="label">Peserta</label><textarea name="attendees" rows="2" class="input" placeholder="Direktur, GM, MR, Kepala Divisi…"></textarea></div>
            <button class="btn-primary">Buat & isi masukan otomatis</button>
        </form>
        @endcan
    </div>
</x-app>
