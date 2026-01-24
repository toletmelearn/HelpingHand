@props([
    'title' => '',
    'value' => '',
    'icon' => '',
    'color' => 'primary',
    'change' => null,
    'changeType' => 'positive' // positive or negative
])

<div {{ $attributes->merge(['class' => 'stat-card card']) }}>
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="stat-card-icon stat-card-icon-{{ $color }}">
                <i class="{{ $icon }}"></i>
            </div>
            <h3 class="stat-card-value mb-0">{{ $value }}</h3>
            <p class="stat-card-title mb-0">{{ $title }}</p>
        </div>
        
        @if($change !== null)
            <div class="text-end">
                <span class="badge badge-hhs badge-hhs-{{ $changeType === 'positive' ? 'success' : 'danger' }}">
                    {{ $changeType === 'positive' ? '+' : '-' }}{{ $change }}%
                </span>
            </div>
        @endif
    </div>
</div>