<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $blocked ? 'Cannot Delete' : 'Delete' }} Bell Timing - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 640px;">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-header {{ $blocked ? 'bg-danger text-white' : 'bg-warning' }}">
                <h5 class="mb-0">
                    <i class="bi {{ $blocked ? 'bi-shield-exclamation' : 'bi-exclamation-triangle' }}"></i>
                    {{ $blocked ? 'Cannot Delete Bell Timing' : 'Delete Bell Timing' }}
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tr><th style="width: 120px;">Class</th><td>{{ $bellTiming->class_section ?? 'All Classes' }}</td></tr>
                    <tr><th>Day</th><td>{{ $bellTiming->day_of_week }}</td></tr>
                    <tr><th>Period</th><td>{{ $bellTiming->period_name }}</td></tr>
                    <tr><th>Time</th><td>{{ $bellTiming->getFormattedTimeRange() }}</td></tr>
                </table>

                @if($blocked)
                    <p>This Bell Timing is currently used by:</p>
                    <ul>
                        @if($dependencies['timetable_slots_published'] > 0)
                            <li>{{ $dependencies['timetable_slots_published'] }} published timetable slot{{ $dependencies['timetable_slots_published'] === 1 ? '' : 's' }}</li>
                        @endif
                        @if($dependencies['timetable_slots_other'] > 0)
                            <li>{{ $dependencies['timetable_slots_other'] }} draft/archived timetable slot{{ $dependencies['timetable_slots_other'] === 1 ? '' : 's' }}</li>
                        @endif
                        @if($dependencies['teacher_substitutions'] > 0)
                            <li>{{ $dependencies['teacher_substitutions'] }} teacher substitution{{ $dependencies['teacher_substitutions'] === 1 ? '' : 's' }}</li>
                        @endif
                        @if($dependencies['teacher_availabilities'] > 0)
                            <li>{{ $dependencies['teacher_availabilities'] }} teacher availability record{{ $dependencies['teacher_availabilities'] === 1 ? '' : 's' }}</li>
                        @endif
                    </ul>
                    @if($dependencies['timetable_slots_published'] > 0)
                        <p class="text-danger"><strong>This schedule is currently used by a published timetable and cannot be deleted.</strong></p>
                    @endif
                    <p class="text-muted small">Resolve these dependencies before deleting.</p>

                    <div class="d-flex gap-2">
                        <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Go Back</a>
                        <a href="{{ route('bell-timing.dependencies', $bellTiming) }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3"></i> View Dependency Details</a>
                    </div>
                @else
                    <p class="text-success"><i class="bi bi-check-circle"></i> This Bell Timing is not currently used by another timetable/dependency.</p>

                    <div class="d-flex gap-2">
                        <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">Cancel</a>
                        <form action="{{ route('bell-timing.destroy', $bellTiming) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
