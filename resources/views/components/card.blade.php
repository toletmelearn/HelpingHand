@props(['title' => '', 'subtitle' => '', 'class' => ''])

<div {{ $attributes->merge(['class' => 'hhs-card card ' . $class]) }}>
    @if($title || $slot->isNotEmpty())
        <div class="hhs-card-header card-header">
            @if($title)<h5 class="hhs-heading mb-0">{{ $title }}</h5>@endif
            @if($subtitle)<p class="hhs-subheading mb-0">{{ $subtitle }}</p>@endif
        </div>
    @endif
    
    <div class="hhs-card-body card-body">
        {{ $slot }}
    </div>
</div>