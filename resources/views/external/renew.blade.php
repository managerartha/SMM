<x-app title="Perpanjang">
    <x-page-header title="Versi baru {{ $doc->number }}" subtitle="{{ $doc->title }}. Versi lama tetap tersimpan sebagai riwayat." />
    <form method="post" action="{{ route('external-documents.renew.store', $doc) }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('external._version')
        <div class="flex gap-2"><button class="btn-primary">Simpan versi baru</button><a href="{{ route('external-documents.show', $doc) }}" class="btn-secondary">Batal</a></div>
    </form>
</x-app>
