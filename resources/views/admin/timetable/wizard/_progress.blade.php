@php
    $steps = [
        1 => 'Class Teachers',
        2 => 'Subjects',
        3 => 'Style',
        4 => 'Check & Create',
    ];
@endphp
<div class="d-flex flex-wrap align-items-center mb-4">
    @foreach($steps as $n => $label)
        <span class="badge {{ $n == ($currentStep ?? 0) ? 'bg-primary' : ($n < ($currentStep ?? 0) ? 'bg-success' : 'bg-secondary') }} me-2 mb-2 px-3 py-2">
            Step {{ $n }}: {{ $label }}
        </span>
        @if($n < 4)
            <span class="text-muted me-2 mb-2">&rarr;</span>
        @endif
    @endforeach
</div>
