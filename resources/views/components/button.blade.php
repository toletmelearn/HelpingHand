@props([
    'variant' => 'primary',
    'size' => 'md',
    'outline' => false,
    'disabled' => false,
    'type' => 'button',
    'href' => null
])

@php
    $classes = [
        'btn-hhs',
        'btn',
        $outline ? 'btn-outline-' . $variant : 'btn-' . $variant,
        'btn-' . $size,
        $disabled ? 'disabled' : ''
    ];
    
    $classString = implode(' ', array_filter($classes));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classString]) }}>
        {{ $slot }}
    </a>
@else
    <button 
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classString]) }}
        @if($disabled) disabled @endif
    >
        {{ $slot }}
    </button>
@endif