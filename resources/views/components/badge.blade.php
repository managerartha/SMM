@props(['color' => 'slate'])
@php
$map = [
    'slate' => 'bg-slate-100 text-slate-700', 'green' => 'bg-emerald-100 text-emerald-800', 'amber' => 'bg-amber-100 text-amber-800',
    'red' => 'bg-red-100 text-red-800', 'blue' => 'bg-blue-100 text-blue-800', 'purple' => 'bg-violet-100 text-violet-800',
];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($map[$color] ?? $map['slate'])]) }}>{{ $slot }}</span>
