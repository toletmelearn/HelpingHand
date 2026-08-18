@extends('layouts.teacher')

@section('title', 'My Weekly Timetable')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="mb-0"><i class="fas fa-calendar-week"></i> My Weekly Timetable</h3>
        <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    @if(!$teacher)
        <div class="alert alert-warning mb-0">
            We couldn't find your teacher profile. Please contact the school office.
        </div>
    @elseif(count($days) === 0)
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-calendar-times fa-2x mb-3"></i>
                <p class="mb-0">No published timetable periods are assigned to you yet.</p>
                <p class="mb-0 small">Once the school publishes the timetable, your weekly schedule will appear here.</p>
            </div>
        </div>
    @else
        @foreach($days as $day)
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-day"></i> {{ $day }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Time</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Room</th>
                                    <th>Co-Teacher</th>
                                    <th>Arrangement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($periodsByDay->get($day, collect()) as $row)
                                    @php($slot = $row->slot)
                                    <tr>
                                        <td>{{ $slot->bellTiming->period_name ?? '' }}</td>
                                        <td>{{ optional($slot->bellTiming->start_time)->format('H:i') }} - {{ optional($slot->bellTiming->end_time)->format('H:i') }}</td>
                                        <td>{{ $slot->schoolClass->name ?? '' }}{{ $slot->section ? ' - ' . $slot->section->name : '' }}</td>
                                        <td>{{ $slot->subject->name ?? '' }}</td>
                                        <td>{{ $slot->room_number ?: '—' }}</td>
                                        <td>{{ $row->with_teacher_name ?? '—' }}</td>
                                        <td>
                                            @if($row->arrangement)
                                                @if($row->arrangement->absent_teacher_id === $slot->teacher_id)
                                                    <span class="badge bg-warning text-dark">Covered by {{ $row->arrangement->substituteTeacher->name ?? 'another teacher' }}</span>
                                                @else
                                                    <span class="badge bg-info text-dark">You are covering</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- UAT Step 5, Scenario 16: this card was previously nested inside the
         @else branch above, so a substitute-only teacher (covering duty
         but no regular timetable of their own, count($days) === 0) fell
         into the "No published timetable" empty state and never saw it,
         even though $coveringAssignments was correctly populated by the
         controller. Moved out here so it renders whenever there's
         something to show, independent of whether the teacher has a
         regular weekly grid. --}}
    @if($teacher && isset($coveringAssignments) && $coveringAssignments->isNotEmpty())
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-people-arrows"></i> Additional Covering Assignments This Week</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Covering For</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($coveringAssignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->substitution_date->format('D, M j') }}</td>
                                    <td>{{ $assignment->bellTiming->period_name ?? $assignment->period_name }}</td>
                                    <td>{{ $assignment->class->name ?? '' }}{{ $assignment->section ? ' - ' . $assignment->section->name : '' }}</td>
                                    <td>{{ $assignment->subject->name ?? '' }}</td>
                                    <td>{{ $assignment->absentTeacher->name ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
