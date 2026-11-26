<table class="sign">
    <tr><td><b>Disahkan oleh</b></td></tr>
    <tr><td>{{ $m->approved_by_name ?? '–' }}{{ $m->approved_by_position ? ', '.$m->approved_by_position : '' }}</td></tr>
    <tr><td class="muted">Disahkan secara elektronik di SMM pada {{ \App\Support\Format::dateTime($m->approved_at) }}</td></tr>
    @if ($m->approval_note)<tr><td class="muted">Catatan: {{ $m->approval_note }}</td></tr>@endif
</table>
