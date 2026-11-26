@if ($files->isNotEmpty())
    <ul class="mt-1 space-y-0.5 text-xs">
        @foreach ($files as $f)
            <li>📎 <a class="link" href="{{ route('attachments.download', $f->uuid) }}">{{ $f->original_name }}</a> <span class="text-slate-400">({{ $f->sizeLabel() }})</span></li>
        @endforeach
    </ul>
@endif
