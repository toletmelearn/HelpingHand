<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reassign Timetable Slot - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 700px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0"><i class="bi bi-arrow-left-right"></i> Reassign Timetable Slot</h1>
            <a href="{{ route('bell-timing.dependencies', $bellTiming) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dependencies
            </a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($recheck)
            <div class="alert {{ $recheck['blocked'] ? 'alert-warning' : 'alert-success' }}">
                <i class="bi {{ $recheck['blocked'] ? 'bi-exclamation-triangle' : 'bi-check-circle' }}"></i>
                @if($recheck['blocked'])
                    <strong>Reassigned.</strong> This Bell Timing is still blocked by: {{ $recheck['summary'] }}.
                @else
                    <strong>Reassigned.</strong> This Bell Timing has no remaining dependencies -- it can now be deleted.
                @endif
                <div class="mt-2">
                    <a href="{{ route('bell-timing.dependencies', $bellTiming) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3"></i> View Dependencies
                    </a>
                    @if(!$recheck['blocked'])
                        <a href="{{ route('bell-timing.delete.confirm', $bellTiming) }}" class="btn btn-sm btn-danger">
                            <i class="bi bi-trash"></i> Go to Delete
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0">Current Slot (unchanged fields)</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width: 160px;">Class / Section</th><td>{{ optional($slot->schoolClass)->name }}{{ $slot->section ? ' / ' . $slot->section->name : '' }}</td></tr>
                    <tr><th>Subject</th><td>{{ optional($slot->subject)->name }}</td></tr>
                    <tr><th>Teacher</th><td>{{ optional($slot->teacher)->name }}{{ $slot->coTeacher ? ' (co: ' . $slot->coTeacher->name . ')' : '' }}</td></tr>
                    <tr><th>Room</th><td>{{ $slot->room_number ?? '—' }}</td></tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @if($slot->status === 'published')
                                <span class="badge bg-danger">Published</span>
                            @else
                                <span class="badge bg-warning text-dark">Draft</span>
                            @endif
                        </td>
                    </tr>
                    <tr><th>Currently at</th><td>{{ $bellTiming->class_section ?? 'All Classes' }} · {{ $bellTiming->day_of_week }} · {{ $bellTiming->period_name }} · {{ $bellTiming->getFormattedTimeRange() }}</td></tr>
                </table>
            </div>
        </div>

        @if($slot->status === 'published')
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>This slot is on a published timetable.</strong> It is currently visible to students, teachers,
                and parents. Reassigning it will change what they see immediately -- there is no separate publish
                step. Only continue if you're sure.
            </div>
        @endif

        <form action="{{ route('timetable.update', $slot) }}" method="POST" id="reassignForm">
            @csrf
            @method('PATCH')
            {{-- Every field below except bell_timing_id is carried forward
                 unchanged -- this form only ever changes WHICH period the
                 lesson sits in, nothing else about it. --}}
            <input type="hidden" name="school_class_id" value="{{ $slot->school_class_id }}">
            <input type="hidden" name="section_id" value="{{ $slot->section_id }}">
            <input type="hidden" name="subject_id" value="{{ $slot->subject_id }}">
            <input type="hidden" name="teacher_id" value="{{ $slot->teacher_id }}">
            <input type="hidden" name="co_teacher_id" value="{{ $slot->co_teacher_id }}">
            <input type="hidden" name="room_number" value="{{ $slot->room_number }}">

            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">New Bell Timing</h5></div>
                <div class="card-body">
                    <label for="bell_timing_id" class="form-label">Replacement Bell Timing *</label>
                    <select class="form-select" id="bell_timing_id" name="bell_timing_id" required>
                        <option value="">Select a Bell Timing</option>
                        @foreach($targets as $target)
                            <option value="{{ $target->id }}">
                                {{ $target->class_section ?? 'All Classes' }} · {{ $target->day_of_week }} · {{ $target->period_name }} · {{ $target->getFormattedTimeRange() }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        You must pick the replacement yourself -- nothing here is pre-selected or suggested.
                        The existing Timetable Editor's own conflict check still runs when you save.
                    </div>

                    @if($slot->status === 'published')
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="confirmPublished">
                            <label class="form-check-label" for="confirmPublished">
                                I understand this will immediately change a published, live timetable.
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" {{ $slot->status === 'published' ? 'disabled' : '' }}>
                <i class="bi bi-check-circle"></i> Save Reassignment
            </button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @if($slot->status === 'published')
        <script>
            // UI safety net only, not a security boundary -- the reused
            // timetable.update endpoint has no knowledge of this checkbox.
            // It exists purely so an admin can't reassign a live published
            // slot from this screen without a deliberate extra step.
            const confirmBox = document.getElementById('confirmPublished');
            const submitBtn = document.getElementById('submitBtn');
            confirmBox.addEventListener('change', () => { submitBtn.disabled = !confirmBox.checked; });
            document.getElementById('reassignForm').addEventListener('submit', function (e) {
                if (!confirm('This will immediately change a published, live timetable slot. Continue?')) {
                    e.preventDefault();
                }
            });
        </script>
    @endif
</body>
</html>
