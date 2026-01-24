@props([
    'type' => 'info',
    'dismissible' => false
])

@php
    $classes = [
        'alert-hhs',
        'alert',
        'alert-' . $type,
        $dismissible ? 'alert-dismissible fade show' : ''
    ];
    
    $classString = implode(' ', $classes);
@endphp

<div {{ $attributes->merge(['class' => $classString]) }} role="alert">
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
    {{ $slot }}
</div>