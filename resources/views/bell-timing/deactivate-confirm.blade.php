<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deactivate Bell Timing - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 640px;">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-slash-circle"></i> Deactivate Bell Timing?</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tr><th style="width: 120px;">Class</th><td>{{ $bellTiming->class_section ?? 'All Classes' }}</td></tr>
                    <tr><th>Day</th><td>{{ $bellTiming->day_of_week }}</td></tr>
                    <tr><th>Period</th><td>{{ $bellTiming->period_name }}</td></tr>
                    <tr><th>Time</th><td>{{ $bellTiming->getFormattedTimeRange() }}</td></tr>
                </table>

                @if(!$bellTiming->is_active)
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This Bell Timing is already inactive.
                    </div>
                @else
                    @if($blocked)
                        <p>This Bell Timing is currently used by:</p>
                        <ul>
                            @foreach($detail['timetable_slots'] as $slot)
                                <li>
                                    {{ $slot['class_name'] ?? '—' }}{{ $slot['section_name'] ? ' / ' . $slot['section_name'] : '' }}
                                    | {{ $slot['subject_name'] ?? '—' }} | {{ $slot['teacher_name'] ?? '—' }}
                                    ({{ ucfirst($slot['status']) }})
                                </li>
                            @endforeach
                            @foreach($detail['teacher_substitutions'] as $sub)
                                <li>Substitution: {{ $sub['absent_teacher_name'] ?? '—' }} on {{ $sub['substitution_date'] ?? '—' }}</li>
                            @endforeach
                            @foreach($detail['teacher_availabilities'] as $avail)
                                <li>Availability record: {{ $avail['teacher_name'] ?? '—' }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Deactivating is not the same as deleting.</strong> It will prevent this Bell Timing
                        from being used in <strong>new</strong> schedules or offered as a reassignment target, but
                        every existing timetable slot, substitution, and availability record listed above -- and its
                        own history -- is left exactly as it is. Nothing is deleted.
                    </div>

                    <form action="{{ route('bell-timing.deactivate', $bellTiming) }}" method="POST">
                        @csrf
                        <div class="d-flex gap-2">
                            <a href="{{ route('bell-timing.dependencies', $bellTiming) }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-slash-circle"></i> Confirm Deactivation
                            </button>
                        </div>
                    </form>
                @endif

                @if(!$bellTiming->is_active)
                    <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                @endif
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
