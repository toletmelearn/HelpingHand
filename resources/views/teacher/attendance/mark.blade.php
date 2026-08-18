@extends('layouts.teacher')

@section('title', 'Mark Attendance')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">Mark Attendance -- {{ $schoolClass->name ?? '' }}</h2>
            <p class="text-muted">{{ $today->format('l, d M Y') }}</p>
        </div>
    </div>

    <div class="alert alert-warning" role="alert">
        Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Students</h5>
                    <p class="card-text display-4">{{ $existingAttendance['total_students'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Present</h5>
                    <p class="card-text display-4">{{ $existingAttendance['present'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Absent</h5>
                    <p class="card-text display-4">{{ $existingAttendance['absent'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Leave</h5>
                    <p class="card-text display-4">{{ $existingAttendance['leave'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Students</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $index => $student)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $student->name ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">No students found for this class.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
