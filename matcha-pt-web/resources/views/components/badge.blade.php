@props(['type' => 'default', 'text' => ''])

@php
    $classes = match($type) {
        'tennis' => 'bg-[#A8E63A]/25 text-[#050608] border-[#7FAF25]/35 font-semibold',
        'padel' => 'bg-[#7FAF25]/20 text-[#050608] border-[#7FAF25]/35 font-semibold',
        'open' => 'bg-[#EBF8D8] text-[#1e4e26] border-[#C4E992] font-semibold',
        'full' => 'bg-slate-100 text-[#666A73] border-slate-200/80',
        'playing' => 'bg-[#EF4444]/10 text-[#EF4444] border-[#EF4444]/30 font-semibold',
        'finished' => 'bg-slate-100 text-[#666A73] border-slate-200',
        'newbie' => 'bg-slate-100 text-[#666A73] border-slate-200',
        'beginner' => 'bg-[#EBF8D8] text-[#1e4e26] border-[#C4E992]',
        'intermediate' => 'bg-[#A8E63A]/25 text-[#050608] border-[#7FAF25]/40 font-semibold',
        'advanced' => 'bg-[#F59E0B]/15 text-[#B45309] border-[#F59E0B]/30 font-semibold',
        default => 'bg-slate-100 text-slate-700 border-slate-200/60',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium border {$classes}"]) }}>
    {{ $slot->isNotEmpty() ? $slot : $text }}
</span>
