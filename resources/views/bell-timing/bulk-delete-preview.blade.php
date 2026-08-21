<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Bulk Delete - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-eye"></i> Bulk Delete Bell Timings</h1>
            <a href="{{ route('bell-timing.bulk-delete') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0">Selected</h5></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($groupsSummary as $g)
                        <li>{{ $g['class_section'] ?? 'All Classes' }} &mdash; {{ $g['day_of_week'] }} &mdash; {{ $g['period_count'] }} period{{ $g['period_count'] == 1 ? '' : 's' }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="alert alert-success">
            <strong>{{ $safeCount }}</strong> Bell Timing{{ $safeCount == 1 ? '' : 's' }} can be deleted.
        </div>

        @if(count($blocked) > 0)
            <div class="alert alert-danger">
                <strong>{{ count($blocked) }}</strong> Bell Timing{{ count($blocked) == 1 ? '' : 's' }} {{ count($blocked) == 1 ? 'is' : 'are' }} blocked:
                <ul class="mb-0 mt-2">
                    @foreach($blocked as $b)
                        <li>
                            {{ $b['bellTiming']->class_section ?? 'All Classes' }} &mdash;
                            {{ $b['bellTiming']->day_of_week }} &mdash;
                            {{ $b['bellTiming']->period_name }} &mdash;
                            {{ $b['reason'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('bell-timing.bulk-delete.confirm') }}" method="POST">
            @csrf
            @foreach($selections as $i => $selection)
                <input type="hidden" name="groups[{{ $i }}][selected]" value="1">
                <input type="hidden" name="groups[{{ $i }}][class_section]" value="{{ $selection['class_section'] }}">
                <input type="hidden" name="groups[{{ $i }}][day_of_week]" value="{{ $selection['day_of_week'] }}">
                <input type="hidden" name="groups[{{ $i }}][academic_year]" value="{{ $selection['academic_year'] }}">
                <input type="hidden" name="groups[{{ $i }}][semester]" value="{{ $selection['semester'] }}">
            @endforeach

            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('bell-timing.bulk-delete') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
                @if($safeCount > 0)
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="bi bi-trash3"></i> Delete {{ $safeCount }} Safe Record{{ $safeCount == 1 ? '' : 's' }}
                    </button>
                @else
                    <button type="button" class="btn btn-danger btn-lg" disabled>Nothing safe to delete</button>
                @endif
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
