@extends('layouts.admin')

@section('title', 'Teacher Attendance Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Attendance Details for {{ $teacher->name ?? 'N/A' }}</h4>
                    <p class="text-muted mb-0">Teacher ID: {{ $teacher->id }} | Subject: {{ $teacher->subject_specialization ?? 'N/A' }}</p>
                </div>
                <div class="card-body">
                    <!-- Monthly Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Days</h5>
                                    <h3>{{ $stats['total_days'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Present</h5>
                                    <h3>{{ $stats['present'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5>Late</h5>
                                    <h3>{{ $stats['late'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Half Day</h5>
                                    <h3>{{ $stats['half_day'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>Absent</h5>
                                    <h3>{{ $stats['absent'] }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h5>Rate</h5>
                                    <h3>{{ $stats['attendance_rate'] }}%</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Options -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('admin.teacher-attendance.show', $teacher->id) }}">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select name="month" class="form-control">
                                            @for($i = 1; $i <= 12; $i++)
                                                <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                                                    {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <select name="year" class="form-control">
                                            @for($i = date('Y') - 2; $i <= date('Y') + 1; $i++)
                                                <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('admin.teacher-attendance.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>

                    <!-- Attendance Records -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Marked By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attendances as $attendance)
                                <tr>
                                    <td>{{ $attendance->date->format('Y-m-d') }}</td>
                                    <td>{{ $attendance->date->format('l') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $attendance->getStatusBadge() }}">
                                            {{ $attendance->getStatusText() }}
                                        </span>
                                    </td>
                                    <td>{{ $attendance->remarks ?? '-' }}</td>
                                    <td>{{ $attendance->markedBy->name ?? 'System' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No attendance records found for this period</td>
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