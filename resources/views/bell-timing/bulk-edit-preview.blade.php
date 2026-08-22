<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Bulk Edit - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    @php
        $readyCount = $preview->where('warning', false)->count();
        $warnedCount = $preview->where('warning', true)->count();
    @endphp
    <div class="container mt-4 mb-5" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-eye"></i> Review Bulk Edit</h1>
            <a href="{{ route('bell-timing.bulk-edit') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Nothing has been changed yet. Review every schedule below, then confirm to apply the update to
            the ones marked Ready or with a warning you accept.
        </div>

        @if($preview->isEmpty() && empty($missing) && empty($ambiguous))
            <div class="alert alert-warning">No schedules matched your selection.</div>
        @else
            <div class="card mb-4">
                @foreach($preview as $p)
                    @php $bt = $p['bellTiming']; @endphp
                    <div class="card-body border-bottom">
                        <h6>
                            {{ $bt->class_section ?? 'All Classes' }} &mdash; {{ $bt->day_of_week }} &mdash; {{ $bt->period_name }}
                        </h6>
                        <div class="row small">
                            <div class="col-md-6">
                                <div class="text-muted">Current</div>
                                <div>{{ $p['old']['start_time'] }} &rarr; {{ $p['old']['end_time'] }}</div>
                                <div>{{ $p['old']['period_name'] }}@if($p['old']['custom_label']) ({{ $p['old']['custom_label'] }})@endif</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted">New</div>
                                <div>{{ $p['new']['start_time'] }} &rarr; {{ $p['new']['end_time'] }}</div>
                                <div>{{ $p['new']['period_name'] }}@if($p['new']['custom_label']) ({{ $p['new']['custom_label'] }})@endif</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            @if($p['warning'])
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> {{ $p['reason'] }}</span>
                            @else
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Ready</span>
                            @endif
                        </div>
                        <input type="hidden" name="known_state[{{ $bt->id }}]" value="{{ $p['known_updated_at'] }}" form="confirmForm">
                    </div>
                @endforeach

                @foreach($missing as $m)
                    <div class="card-body border-bottom bg-light">
                        <h6>{{ $m['class_section'] ?? 'All Classes' }} &mdash; {{ $m['day_of_week'] }} &mdash; {{ $payload['target_period_name'] }}</h6>
                        <div class="text-muted">Not found</div>
                        <span class="badge bg-secondary"><i class="bi bi-exclamation-triangle"></i> Not updated</span>
                    </div>
                @endforeach

                @foreach($ambiguous as $a)
                    <div class="card-body border-bottom bg-light">
                        <h6>{{ $a['class_section'] ?? 'All Classes' }} &mdash; {{ $a['day_of_week'] }} &mdash; {{ $payload['target_period_name'] }}</h6>
                        <div class="text-muted">Ambiguous match &mdash; {{ $a['count'] }} periods share this name</div>
                        <span class="badge bg-secondary"><i class="bi bi-exclamation-triangle"></i> Not updated</span>
                    </div>
                @endforeach
            </div>

            <div class="alert alert-success">
                <strong>{{ $readyCount }}</strong> ready, <strong>{{ $warnedCount }}</strong> with a warning
                (still editable if you confirm), <strong>{{ count($missing) + count($ambiguous) }}</strong>
                will not be updated.
            </div>

            <form action="{{ route('bell-timing.bulk-edit.confirm') }}" method="POST" id="confirmForm">
                @csrf
                @foreach($selections as $i => $selection)
                    <input type="hidden" name="groups[{{ $i }}][selected]" value="1">
                    <input type="hidden" name="groups[{{ $i }}][class_section]" value="{{ $selection['class_section'] }}">
                    <input type="hidden" name="groups[{{ $i }}][day_of_week]" value="{{ $selection['day_of_week'] }}">
                    <input type="hidden" name="groups[{{ $i }}][academic_year]" value="{{ $selection['academic_year'] }}">
                    <input type="hidden" name="groups[{{ $i }}][semester]" value="{{ $selection['semester'] }}">
                @endforeach

                <input type="hidden" name="target_period_name" value="{{ $payload['target_period_name'] }}">

                @if(($payload['change_time'] ?? null) === '1')
                    <input type="hidden" name="change_time" value="1">
                    <input type="hidden" name="new_start_time" value="{{ $payload['new_start_time'] }}">
                    <input type="hidden" name="new_end_time" value="{{ $payload['new_end_time'] }}">
                @endif
                @if(($payload['change_period_name'] ?? null) === '1')
                    <input type="hidden" name="change_period_name" value="1">
                    <input type="hidden" name="new_period_name" value="{{ $payload['new_period_name'] }}">
                @endif
                @if(($payload['change_custom_label'] ?? null) === '1')
                    <input type="hidden" name="change_custom_label" value="1">
                    <input type="hidden" name="new_custom_label" value="{{ $payload['new_custom_label'] }}">
                @endif
                @if(($payload['change_color_code'] ?? null) === '1')
                    <input type="hidden" name="change_color_code" value="1">
                    <input type="hidden" name="new_color_code" value="{{ $payload['new_color_code'] }}">
                @endif

                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('bell-timing.bulk-edit') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
                    @if($readyCount + $warnedCount > 0)
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-check-circle"></i> Confirm Update
                        </button>
                    @else
                        <button type="button" class="btn btn-warning btn-lg" disabled>Nothing to update</button>
                    @endif
                </div>
            </form>
        @endif
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
