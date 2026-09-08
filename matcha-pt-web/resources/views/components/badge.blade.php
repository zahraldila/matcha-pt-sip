@props(['type' => 'default', 'text' => ''])

@php
    $classes = match($type) {
        'tennis' => 'bg-lime-50 text-lime-900 border-lime-200/80',
        'padel' => 'bg-emerald-50 text-emerald-900 border-emerald-200/80',
        'open' => 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
        'full' => 'bg-slate-100 text-slate-700 border-slate-200/80',
        'playing' => 'bg-rose-50 text-rose-800 border-rose-200/60',
        'finished' => 'bg-slate-100 text-slate-600 border-slate-200',
        'newbie' => 'bg-slate-100 text-slate-700 border-slate-200',
        'beginner' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'intermediate' => 'bg-[#e4f3e6] text-[#1e4e26] border-[#b4dcba]',
        'advanced' => 'bg-amber-50 text-amber-900 border-amber-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200/60',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium border {$classes}"]) }}>
    {{ $slot->isNotEmpty() ? $slot : $text }}
</span>
