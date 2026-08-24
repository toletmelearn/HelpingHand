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
    <div class="container mt-4 mb-5" style="max-width: 900px;">
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
            <strong>{{ $safeCount }}</strong> Bell Timing{{ $safeCount == 1 ? '' : 's' }} ready to delete.
        </div>

        @if(count($blocked) > 0)
            <div class="alert alert-warning">
                <strong>{{ count($blocked) }}</strong> Bell Timing{{ count($blocked) == 1 ? '' : 's' }}
                {{ count($blocked) == 1 ? 'requires' : 'require' }} attention before {{ count($blocked) == 1 ? 'it' : 'they' }} can be deleted.
                Resolve each one below, or leave it alone (Skip) and continue with the rest.
            </div>

            @foreach($blocked as $b)
                @php $bt = $b['bellTiming']; @endphp
                <div class="card mb-3 border-warning" id="blocked-{{ $bt->id }}">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-exclamation-triangle"></i>
                            {{ $bt->class_section ?? 'All Classes' }} &mdash; {{ $bt->day_of_week }} &mdash;
                            {{ $bt->period_name }} &mdash; {{ $bt->getFormattedTimeRange() }}
                        </span>
                        <span class="small">{{ $b['reason'] }}</span>
                    </div>
                    <div class="card-body">
                        @if(count($b['detail']['timetable_slots']) > 0)
                            <div class="mb-2">
                                <strong><i class="bi bi-calendar3"></i> Timetable Slots</strong>
                                <ul class="mb-1">
                                    @foreach($b['detail']['timetable_slots'] as $slot)
                                        <li>
                                            {{ $slot['class_name'] ?? '—' }}{{ $slot['section_name'] ? ' / ' . $slot['section_name'] : '' }}
                                            | {{ $slot['subject_name'] ?? '—' }} | {{ $slot['teacher_name'] ?? '—' }}
                                            &mdash; <span class="badge {{ $slot['status'] === 'published' ? 'bg-danger' : ($slot['status'] === 'archived' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ ucfirst($slot['status']) }}</span>
                                            @if($slot['is_locked'])
                                                <span class="badge bg-dark"><i class="bi bi-lock-fill"></i> Locked</span>
                                            @endif
                                            @if($slot['reassignable'])
                                                <a href="{{ route('bell-timing.dependencies.reassign-slot', [$bt, $slot['id']]) }}" class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="bi bi-arrow-left-right"></i> Reassign
                                                </a>
                                            @else
                                                <span class="text-muted small">(not reassignable)</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(count($b['detail']['teacher_substitutions']) > 0)
                            <div class="mb-2">
                                <strong><i class="bi bi-arrow-left-right"></i> Teacher Substitutions</strong>
                                <ul class="mb-1">
                                    @foreach($b['detail']['teacher_substitutions'] as $sub)
                                        <li>
                                            {{ $sub['absent_teacher_name'] ?? '—' }} on {{ $sub['substitution_date'] ?? '—' }}
                                            &mdash; <span class="badge bg-secondary text-uppercase">{{ $sub['status'] ?? 'unknown' }}</span>
                                            <a href="{{ route('bell-timing.dependencies.reassign-substitution', [$bt, $sub['id']]) }}" class="btn btn-sm btn-outline-primary ms-1">
                                                <i class="bi bi-arrow-left-right"></i> Reassign
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(count($b['detail']['teacher_availabilities']) > 0)
                            <div class="mb-2">
                                <strong><i class="bi bi-calendar2-week"></i> Teacher Availability</strong>
                                <ul class="mb-1">
                                    @foreach($b['detail']['teacher_availabilities'] as $avail)
                                        <li>
                                            {{ $avail['teacher_name'] ?? '—' }}
                                            @if(Route::has('teacher-availability.edit') && $avail['teacher_id'])
                                                <a href="{{ route('teacher-availability.edit', $avail['teacher_id']) }}" class="btn btn-sm btn-outline-secondary ms-1">
                                                    <i class="bi bi-box-arrow-up-right"></i> Manage in Availability Grid
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex gap-2 mt-2">
                            <a href="{{ route('bell-timing.dependencies', $bt) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-diagram-3"></i> View Dependencies
                            </a>
                            <a href="{{ route('bell-timing.deactivate.confirm', $bt) }}" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-slash-circle"></i> Deactivate Instead
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="skipRow({{ $bt->id }})">
                                <i class="bi bi-eye-slash"></i> Skip
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
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
            <p class="text-muted small mt-2">
                Clicking delete re-checks every record's dependencies one more time immediately before deleting --
                if anything above changed in the meantime, it will be protected rather than deleted, and you'll be
                told exactly what happened.
            </p>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Skip is purely a client-side viewing aid -- it performs no
        // request and changes no data. A blocked record is already
        // excluded from deletion by the server regardless of whether it's
        // "skipped" here; this just lets the admin visually collapse a
        // row they've decided to leave alone for now while reviewing the
        // rest of the list.
        function skipRow(id) {
            const card = document.getElementById('blocked-' + id);
            if (card) {
                card.style.opacity = '0.5';
                card.querySelector('.card-body').style.display = 'none';
            }
        }
    </script>
</body>
</html>
