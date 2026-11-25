@props(['status'])
@php
$c = ['draf' => 'slate', 'review' => 'blue', 'pengesahan' => 'purple', 'berlaku' => 'green', 'kedaluwarsa' => 'amber', 'dibatalkan' => 'red'][$status] ?? 'slate';
@endphp
<x-badge :color="$c">{{ App\Models\DocumentRevision::LABELS[$status] ?? $status }}</x-badge>
