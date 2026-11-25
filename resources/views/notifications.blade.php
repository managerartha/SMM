@php use App\Support\Format; @endphp
<x-app title="Notifikasi">
    <x-page-header title="Notifikasi">
        <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary btn-sm">Tandai semua sudah dibaca</button></form>
    </x-page-header>
    <div class="card">
        <ul class="divide-y text-sm">
            @forelse ($notifications as $n)
                <li class="px-4 py-3 {{ $n->read_at ? '' : 'bg-brand-50' }}">
                    <a href="{{ route('notifications.open', $n->id) }}" class="block">
                        <p class="font-medium {{ $n->read_at ? 'text-slate-700' : 'text-brand-800' }}">{{ $n->data['subject'] ?? 'Notifikasi' }}</p>
                        <p class="text-slate-600">{{ implode(' ', $n->data['lines'] ?? []) }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">{{ Format::dateTime($n->created_at) }}</p>
                    </a>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-slate-500">Belum ada notifikasi.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app>
