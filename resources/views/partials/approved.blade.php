@if ($m->approved_at)
    <p class="text-sm">Disahkan oleh <b>{{ $m->approved_by_name }}</b>{{ $m->approved_by_position ? ' ('.$m->approved_by_position.')' : '' }} pada {{ \App\Support\Format::dateTime($m->approved_at) }}.
        @if (! empty($m->file_id) && $m->file)<a class="link" href="{{ route('attachments.download', $m->file->uuid) }}">Unduh PDF resmi</a>@endif</p>
@elseif ($m->approval_note)
    <p class="rounded bg-amber-50 p-2 text-sm text-amber-800">Dikembalikan: {{ $m->approval_note }}</p>
@endif
