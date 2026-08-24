<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bell Timing Dependencies - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 760px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0"><i class="bi bi-diagram-3"></i> Bell Timing Dependencies</h1>
            <a href="{{ route('bell-timing.delete.confirm', $bellTiming) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0">Bell Timing</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width: 120px;">Class</th><td>{{ $bellTiming->class_section ?? 'All Classes' }}</td></tr>
                    <tr><th>Day</th><td>{{ $bellTiming->day_of_week }}</td></tr>
                    <tr><th>Period</th><td>{{ $bellTiming->period_name }}</td></tr>
                    <tr><th>Time</th><td>{{ $bellTiming->getFormattedTimeRange() }}</td></tr>
                </table>
            </div>
        </div>

        @if(!$blocked)
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> This Bell Timing has no dependencies -- it is safe to delete.
            </div>
        @else
            <p class="text-muted">
                Every record below must be resolved (reassigned elsewhere or removed through its own screen) before
                this Bell Timing can be deleted. Nothing on this page changes anything -- it's read-only.
            </p>

            @if(count($detail['timetable_slots']) > 0)
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> Timetable Slots ({{ count($detail['timetable_slots']) }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Class / Section</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detail['timetable_slots'] as $slot)
                                    <tr>
                                        <td>{{ $slot['class_name'] ?? '—' }}{{ $slot['section_name'] ? ' / ' . $slot['section_name'] : '' }}</td>
                                        <td>{{ $slot['subject_name'] ?? '—' }}</td>
                                        <td>
                                            {{ $slot['teacher_name'] ?? '—' }}
                                            @if($slot['co_teacher_name'])
                                                <span class="text-muted">(co: {{ $slot['co_teacher_name'] }})</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($slot['status'] === 'published')
                                                <span class="badge bg-danger">Published</span>
                                            @elseif($slot['status'] === 'archived')
                                                <span class="badge bg-secondary">Archived</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Draft</span>
                                            @endif
                                            @if($slot['is_locked'])
                                                <span class="badge bg-dark"><i class="bi bi-lock-fill"></i> Locked</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($slot['reassignable'])
                                                <a href="{{ route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot['id']]) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-left-right"></i> Reassign
                                                </a>
                                            @else
                                                <span class="text-danger small"><i class="bi bi-x-circle"></i> Not reassignable</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(collect($detail['timetable_slots'])->contains(fn ($s) => $s['status'] === 'published'))
                        <div class="card-footer bg-danger-subtle">
                            <i class="bi bi-exclamation-triangle text-danger"></i>
                            <strong>At least one slot above is on a published timetable</strong> -- it is currently visible
                            to students, teachers, and parents.
                        </div>
                    @endif
                    @if(collect($detail['timetable_slots'])->contains(fn ($s) => !$s['reassignable']))
                        <div class="card-footer">
                            <i class="bi bi-info-circle"></i>
                            Slots marked "Not reassignable" are archived and/or locked -- they cannot be edited from
                            the Timetable Editor at all, archived slots are permanent history and locked slots must be
                            unlocked there first.
                        </div>
                    @endif
                </div>
            @endif

            @if(count($detail['teacher_substitutions']) > 0)
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Teacher Substitutions ({{ count($detail['teacher_substitutions']) }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Absent Teacher</th>
                                    <th>Class / Section</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detail['teacher_substitutions'] as $sub)
                                    <tr>
                                        <td>{{ $sub['substitution_date'] ?? '—' }}</td>
                                        <td>{{ $sub['absent_teacher_name'] ?? '—' }}</td>
                                        <td>{{ $sub['class_name'] ?? '—' }}{{ $sub['section_name'] ? ' / ' . $sub['section_name'] : '' }}</td>
                                        <td>{{ $sub['subject_name'] ?? '—' }}</td>
                                        <td><span class="badge bg-secondary text-uppercase">{{ $sub['status'] ?? 'unknown' }}</span></td>
                                        <td>
                                            <a href="{{ route('bell-timing.dependencies.reassign-substitution', [$bellTiming, $sub['id']]) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-arrow-left-right"></i> Reassign
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <i class="bi bi-info-circle"></i>
                        A cancelled substitution still counts as a dependency here -- only reassigning it to a
                        different Bell Timing, or deleting the substitution itself, clears this.
                    </div>
                </div>
            @endif

            @if(count($detail['teacher_availabilities']) > 0)
                <div class="card mb-3">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar2-week"></i> Teacher Availability Records ({{ count($detail['teacher_availabilities']) }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Teacher</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach($detail['teacher_availabilities'] as $avail)
                                    <tr>
                                        <td>{{ $avail['teacher_name'] ?? '—' }}</td>
                                        <td>
                                            @if(Route::has('teacher-availability.edit') && $avail['teacher_id'])
                                                <a href="{{ route('teacher-availability.edit', $avail['teacher_id']) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-box-arrow-up-right"></i> Manage in Availability Grid
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <i class="bi bi-info-circle"></i>
                        Availability is managed as a whole grid per teacher, not one record at a time -- there is no
                        safe way to reassign just this row without risking an unrelated change made to the same grid
                        in between. Use the link above to edit it directly.
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('bell-timing.delete.confirm', $bellTiming) }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Keep / Back
                </a>
                <a href="{{ route('bell-timing.deactivate.confirm', $bellTiming) }}" class="btn btn-outline-warning">
                    <i class="bi bi-slash-circle"></i> Deactivate Instead
                </a>
            </div>
        @endif
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
