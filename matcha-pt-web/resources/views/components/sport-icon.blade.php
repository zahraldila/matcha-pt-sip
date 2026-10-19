@props([
    'sport' => 'all',
    'class' => 'w-3.5 h-3.5',
])

@php
    $normalized = strtolower(trim((string) $sport));
@endphp

@if($normalized === 'tennis')
    <svg {{ $attributes->merge(['class' => "$class inline-block shrink-0", 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.9', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }} aria-hidden="true">
        <!-- Tennis Racquet -->
        <ellipse cx="14.5" cy="9.5" rx="6" ry="7.2" transform="rotate(45 14.5 9.5)"/>
        <!-- Strings -->
        <path d="M12 5.5 L19 12.5" stroke-width="1.1" stroke-opacity="0.8"/>
        <path d="M9.5 8 L16.5 15" stroke-width="1.1" stroke-opacity="0.8"/>
        <path d="M14.5 4 L7.5 11" stroke-width="1.1" stroke-opacity="0.8"/>
        <path d="M17 6.5 L10 13.5" stroke-width="1.1" stroke-opacity="0.8"/>
        <!-- Throat & Grip -->
        <path d="M9 15 L6 18"/>
        <path d="M6 18 L2.5 21.5" stroke-width="2.7"/>
    </svg>
@elseif($normalized === 'padel')
    <svg {{ $attributes->merge(['class' => "$class inline-block shrink-0", 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.9', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }} aria-hidden="true">
        <!-- Padel Racquet (Solid teardrop head with signature perforations) -->
        <rect x="8" y="3" width="13" height="13" rx="6.5" transform="rotate(45 14.5 9.5)"/>
        <!-- Perforation Holes -->
        <circle cx="12.5" cy="7.5" r="0.75" fill="currentColor" stroke="none"/>
        <circle cx="15.5" cy="7.5" r="0.75" fill="currentColor" stroke="none"/>
        <circle cx="14" cy="9" r="0.75" fill="currentColor" stroke="none"/>
        <circle cx="12.5" cy="10.5" r="0.75" fill="currentColor" stroke="none"/>
        <circle cx="15.5" cy="10.5" r="0.75" fill="currentColor" stroke="none"/>
        <circle cx="14" cy="12" r="0.75" fill="currentColor" stroke="none"/>
        <!-- Throat & Grip -->
        <path d="M9 15 L6 18"/>
        <path d="M6 18 L2.5 21.5" stroke-width="2.7"/>
    </svg>
@elseif($normalized === 'ball' || $normalized === 'tennis-ball')
    <svg {{ $attributes->merge(['class' => "$class inline-block shrink-0", 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '2', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }} aria-hidden="true">
        <circle cx="12" cy="12" r="9"/>
        <path d="M18.36 5.64A9 9 0 0 1 5.64 18.36"/>
        <path d="M5.64 5.64A9 9 0 0 0 18.36 18.36"/>
    </svg>
@else
    <i {{ $attributes->merge(['class' => "$class fa-solid fa-layer-group inline-block shrink-0"]) }} aria-hidden="true"></i>
@endif
