@extends('layouts.admin')

@section('title', 'Attendance Reports')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-bar-chart-line"></i> Attendance Reports</h2>
        <div>
            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Attendance
            </a>
            <a href="{{ route('attendance.export') }}" class="btn btn-success">
                <i class="bi bi-download"></i> Export Data
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Reports</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.reports') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="{{ request('date', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="class" class="form-label">Class</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">All Classes</option>
                            @foreach($classes as $className)
                                <option value="{{ $className }}" {{ request('class') == $className ? 'selected' : '' }}>
                                    {{ $className }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Overview -->
    @if(isset($stats))
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary">{{ $stats['total'] }}</h3>
                    <p class="card-text">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success">{{ $stats['present'] }}</h3>
                    <p class="card-text">Present</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-danger">{{ $stats['absent'] }}</h3>
                    <p class="card-text">Absent</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning">{{ $stats['late'] }}</h3>
                    <p class="card-text">Late</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-percent"></i> Attendance Percentage: {{ $stats['percentage'] }}%</h5>
                </div>
                <div class="card-body">
                    <p class="text-center">
                        <strong>{{ $stats['percentage'] }}%</strong> attendance rate
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Detailed Attendance Records -->
    @if(isset($attendances) && $attendances->count() > 0)
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-list"></i> Detailed Attendance Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Roll Number</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Subject</th>
                            <th>Period</th>
                            <th>Marked By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->student->name ?? 'N/A' }}</td>
                            <td>{{ $attendance->student->roll_number ?? 'N/A' }}</td>
                            <td>
                                @if($attendance->status == 'present')
                                    <span class="badge bg-success">Present</span>
                                @elseif($attendance->status == 'absent')
                                    <span class="badge bg-danger">Absent</span>
                                @elseif($attendance->status == 'late')
                                    <span class="badge bg-warning">Late</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($attendance->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $attendance->remarks ?? '-' }}</td>
                            <td>{{ $attendance->subject ?? '-' }}</td>
                            <td>{{ $attendance->period ?? '-' }}</td>
                            <td>{{ $attendance->markedBy->name ?? 'System' }}</td>
                            <td>{{ $attendance->date->format('M j, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @elseif(request()->hasAny(['date', 'class']))
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No attendance records found for the selected criteria.
    </div>
    @endif
</div>
@endsection
