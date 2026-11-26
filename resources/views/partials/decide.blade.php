{{-- Formulir keputusan pengesahan bersama. $action, $yes (label), $yesValue, $noValue --}}
<form method="post" action="{{ $action }}" class="space-y-2 rounded-md bg-violet-50 p-3" x-data="{ d: '{{ $yesValue ?? 'sahkan' }}' }">@csrf
    <p class="text-sm font-medium">{{ $title ?? 'Pengesahan' }}</p>
    <p class="text-xs text-slate-600">Nama, jabatan, dan waktu Anda dicatat sebagai bukti pengesahan; PDF resmi dibuat otomatis.</p>
    <div class="flex gap-4 text-sm">
        <label class="flex items-center gap-2"><input type="radio" name="decision" value="{{ $yesValue ?? 'sahkan' }}" x-model="d"> {{ $yes ?? 'Sahkan' }}</label>
        <label class="flex items-center gap-2"><input type="radio" name="decision" value="tolak" x-model="d"> {{ $no ?? 'Kembalikan / tolak' }}</label>
    </div>
    <textarea name="note" rows="2" class="input" :required="d === 'tolak'" :placeholder="d === 'tolak' ? 'Alasan (wajib)' : 'Catatan (opsional)'"></textarea>
    <x-field-error name="note" />
    <button class="btn-success">Kirim keputusan</button>
</form>
