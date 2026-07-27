@extends('layouts.admin')

@section('title', 'Teacher Absent Today - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-start">
            <div>
                <h2 class="mb-0"><i class="fas fa-user-clock"></i> Teacher Absent Today</h2>
                <p class="text-muted mb-0">Pick a teacher and date to see their slots for that day and ranked substitute suggestions.</p>
            </div>
            <a href="{{ route('admin.teacher-substitutions.arrangement-sheet', ['date' => $date->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-pdf"></i> Arrangement Sheet PDF
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.teacher-substitutions.absent-today') }}" method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="teacher_id" class="form-label fw-bold">Absent Teacher</label>
                    <select name="teacher_id" id="teacher_id" class="form-select" required>
                        <option value="">-- Choose Teacher --</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" {{ optional($selectedTeacher)->id == $teacher->id ? 'selected' : '' }}>
                                {{ $teacher->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="date" class="form-label fw-bold">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="{{ $date->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Load</button>
                </div>
            </form>
        </div>
    </div>

    @if($selectedTeacher)
        <h5 class="mb-3">{{ $selectedTeacher->name }} — {{ $date->format('d F Y (l)') }}</h5>

        @if(empty($rows))
            <div class="alert alert-info">This teacher has no timetable slots on {{ $date->format('l') }}.</div>
        @endif

        @foreach($rows as $row)
            @php $slot = $row['slot']; @endphp
            <div class="card shadow mb-3">
                <div class="card-header bg-light">
                    <strong>{{ $slot->bellTiming->period_name }}</strong>
                    <span class="text-muted">({{ $slot->bellTiming->start_time->format('H:i') }}-{{ $slot->bellTiming->end_time->format('H:i') }})</span>
                    &mdash;
                    {{ $slot->schoolClass->name ?? '?' }}{{ $slot->section ? ' '.$slot->section->name : '' }}
                    &mdash; {{ $slot->subject->name ?? '?' }}
                </div>
                <div class="card-body">
                    @if($row['existing'])
                        <p class="mb-0">
                            <span class="badge bg-info">{{ $row['existing']->getReadableStatus() }}</span>
                            Substitute:
                            <strong>{{ $row['existing']->substituteTeacher->name ?? 'Not yet assigned' }}</strong>
                            <a href="{{ route('admin.teacher-substitutions.show', $row['existing']) }}" class="ms-2">View</a>
                        </p>
                    @elseif(empty($row['candidates']))
                        <p class="text-muted mb-0">No free, matching substitute teachers found for this period.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Score</th>
                                        <th>Reasons</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['candidates'] as $candidate)
                                        <tr>
                                            <td>{{ $candidate['teacher']->name }}</td>
                                            <td>{{ $candidate['score'] }}</td>
                                            <td>{{ $candidate['reason_text'] }}</td>
                                            <td class="text-end">
                                                <form action="{{ route('admin.teacher-substitutions.assign-from-slot') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="substitution_date" value="{{ $date->format('Y-m-d') }}">
                                                    <input type="hidden" name="absent_teacher_id" value="{{ $selectedTeacher->id }}">
                                                    <input type="hidden" name="class_id" value="{{ $slot->school_class_id }}">
                                                    <input type="hidden" name="section_id" value="{{ $slot->section_id }}">
                                                    <input type="hidden" name="subject_id" value="{{ $slot->subject_id }}">
                                                    <input type="hidden" name="bell_timing_id" value="{{ $slot->bell_timing_id }}">
                                                    <input type="hidden" name="substitute_teacher_id" value="{{ $candidate['teacher']->id }}">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-user-check"></i> Assign
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
