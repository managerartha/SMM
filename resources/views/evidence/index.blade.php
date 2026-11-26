@php use App\Support\Format; @endphp
<x-app title="Paket Bukti">
    <x-page-header title="Paket Bukti Penilaian PJK3" subtitle="Satu ZIP berisi 16 folder bukti + 00_Daftar_Isi.pdf, disusun otomatis dari seluruh data SMM" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>Dibuat</th><th>Periode</th><th>Oleh</th><th>Status</th><th>Berkas</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($packages as $p)
                    <tr><td class="whitespace-nowrap">{{ Format::dateTime($p->created_at) }}</td><td class="whitespace-nowrap">{{ Format::date($p->date_from) }} – {{ Format::date($p->date_to) }}</td><td>{{ $p->creator?->name }}</td>
                        <td><x-badge :color="['antri' => 'slate', 'proses' => 'blue', 'selesai' => 'green', 'gagal' => 'red'][$p->status]">{{ ucfirst($p->status) }}</x-badge>
                            @if ($p->status === 'gagal')<p class="mt-1 max-w-xs text-xs text-red-700">{{ \Illuminate\Support\Str::limit($p->error, 200) }}</p>@endif</td>
                        <td>@if ($p->file)<a class="link" href="{{ route('attachments.download', $p->file->uuid) }}">Unduh ZIP</a> <span class="text-xs text-slate-400">({{ $p->file->sizeLabel() }})</span>
                            @if (! empty($p->summary['folders']))<p class="text-xs text-slate-500">{{ array_sum($p->summary['folders']) }} berkas</p>@endif
                            @if (! empty($p->summary['warnings']))<details class="text-xs text-amber-700"><summary>{{ count($p->summary['warnings']) }} catatan</summary><ul class="list-disc pl-4">@foreach ($p->summary['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></details>@endif
                            @else – @endif</td></tr>
                @empty <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada Paket Bukti.</td></tr> @endforelse
                </tbody>
            </table>
        </section>
        <div class="space-y-5">
            @can('paket.buat')
            <form method="post" action="{{ route('evidence.store') }}" class="card card-body space-y-3">@csrf
                <h2 class="font-semibold">Buat Paket Bukti</h2>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">Dari</label><input type="date" name="date_from" required value="{{ now()->subYear()->toDateString() }}" class="input"></div>
                    <div><label class="label">s.d.</label><input type="date" name="date_to" required value="{{ now()->toDateString() }}" class="input"><x-field-error name="date_to" /></div>
                </div>
                <button class="btn-primary">Buat paket</button>
                <p class="help">Dibuat di latar belakang lewat cron (beberapa menit) dan disimpan di Google Drive folder "Paket Bukti". Dokumen terkendali diberi tanda air "SALINAN TERKENDALI".</p>
            </form>
            @endcan
            <section class="card card-body text-sm">
                <h2 class="mb-2 font-semibold">Isi folder</h2>
                <ol class="space-y-0.5 text-slate-700">@foreach ($categories as $c)<li>{{ str_pad($c->id, 2, '0', STR_PAD_LEFT) }} – {{ $c->name }}</li>@endforeach</ol>
            </section>
        </div>
    </div>
</x-app>
