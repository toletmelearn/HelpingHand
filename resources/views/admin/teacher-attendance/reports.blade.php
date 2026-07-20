@extends('layouts.admin')

@section('title', 'Teacher Attendance Reports')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Teacher Attendance Reports</h4>
                    <p class="text-muted mb-0">View attendance reports for {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</p>
                </div>
                <div class="card-body">
                    <!-- Attendance Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Teachers</h5>
                                    <h2>{{ $stats['total'] }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Present</h5>
                                    <h2>{{ $stats['present'] }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>Absent</h5>
                                    <h2>{{ $stats['absent'] }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Attendance Rate</h5>
                                    <h2>{{ $stats['attendance_rate'] }}%</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('admin.teacher-attendance.reports') }}">
                                <div class="input-group">
                                    <input type="date" name="date" class="form-control" value="{{ $date }}">
                                    <button class="btn btn-primary" type="submit">Filter</button>
                                    <a href="{{ route('admin.teacher-attendance.reports') }}" class="btn btn-secondary">Today</a>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('admin.teacher-attendance.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Attendance
                            </a>
                        </div>
                    </div>

                    <!-- Detailed Report -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Teacher ID</th>
                                    <th>Name</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachers as $teacher)
                                <tr>
                                    <td>{{ $teacher->id }}</td>
                                    <td>{{ $teacher->name ?? 'N/A' }}</td>
                                    <td>{{ $teacher->subject_specialization ?? 'N/A' }}</td>
                                    <td>
                                        @if($teacher->teacherAttendances->first())
                                            <span class="badge bg-{{ $teacher->teacherAttendances->first()->getStatusBadge() }}">
                                                {{ $teacher->teacherAttendances->first()->getStatusText() }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">Not Marked</span>
                                        @endif
                                    </td>
                                    <td>{{ $teacher->teacherAttendances->first()?->remarks ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No teachers found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection