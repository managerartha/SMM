<table>
    <tr><th>Kode</th><th>KPI</th><th>Target</th><th>Realisasi</th><th>Status</th></tr>
    @forelse ($kpis as $k)
        <tr><td>{{ $k['code'] }}</td><td>{{ $k['name'] }}</td><td>{{ $k['target'] }}</td>
            <td>{{ $k['average'] ?? '–' }} {{ $k['average'] !== null ? $k['unit'] : '' }}@if ($k['months'] > 1)<br><span class="muted">{{ $k['months_met'] }}/{{ $k['months'] }} bulan tercapai</span>@endif</td>
            <td class="{{ $k['achieved'] === null ? '' : ($k['achieved'] ? 'ok' : 'bad') }}">{{ $k['achieved'] === null ? 'Belum ada data' : ($k['achieved'] ? 'Tercapai' : 'Tidak tercapai') }}</td></tr>
    @empty <tr><td colspan="5">Belum ada KPI.</td></tr> @endforelse
</table>
