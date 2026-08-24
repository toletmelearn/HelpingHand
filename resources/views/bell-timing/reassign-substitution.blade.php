<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reassign Teacher Substitution - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 700px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0"><i class="bi bi-arrow-left-right"></i> Reassign Teacher Substitution</h1>
            <a href="{{ route('bell-timing.dependencies', $bellTiming) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dependencies
            </a>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Saving here takes you to the Teacher Substitutions list (that screen's own existing behavior). Come back
            and click <strong>View Dependencies</strong> below afterward to confirm this Bell Timing can now be deleted.
        </div>

        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0">Current Substitution (unchanged fields)</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width: 160px;">Date</th><td>{{ optional($substitution->substitution_date)->toDateString() }}</td></tr>
                    <tr><th>Absent Teacher</th><td>{{ optional($substitution->absentTeacher)->name }}</td></tr>
                    <tr><th>Class / Section</th><td>{{ optional($substitution->class)->name }}{{ $substitution->section ? ' / ' . $substitution->section->name : '' }}</td></tr>
                    <tr><th>Subject</th><td>{{ optional($substitution->subject)->name }}</td></tr>
                    <tr><th>Status</th><td><span class="badge bg-secondary text-uppercase">{{ $substitution->status }}</span></td></tr>
                    <tr><th>Currently at</th><td>{{ $bellTiming->class_section ?? 'All Classes' }} · {{ $bellTiming->day_of_week }} · {{ $bellTiming->period_name }} · {{ $bellTiming->getFormattedTimeRange() }}</td></tr>
                </table>
            </div>
        </div>

        <form action="{{ route('admin.teacher-substitutions.update', $substitution) }}" method="POST">
            @csrf
            @method('PUT')
            {{-- Every field below except bell_timing_id is carried forward
                 unchanged -- this only moves which period the substitution
                 covers, nothing else about it. --}}
            <input type="hidden" name="substitution_date" value="{{ optional($substitution->substitution_date)->toDateString() }}">
            <input type="hidden" name="absent_teacher_id" value="{{ $substitution->absent_teacher_id }}">
            <input type="hidden" name="class_id" value="{{ $substitution->class_id }}">
            <input type="hidden" name="section_id" value="{{ $substitution->section_id }}">
            <input type="hidden" name="subject_id" value="{{ $substitution->subject_id }}">
            <input type="hidden" name="status" value="{{ $substitution->status }}">
            <input type="hidden" name="substitute_teacher_id" value="{{ $substitution->substitute_teacher_id }}">
            <input type="hidden" name="reason" value="{{ $substitution->reason }}">

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
                    <div class="form-text">You must pick the replacement yourself -- nothing here is pre-selected or suggested.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Reassignment</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
