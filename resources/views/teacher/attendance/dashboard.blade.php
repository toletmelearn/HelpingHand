@extends('layouts.teacher')

@section('title', 'Attendance Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">Attendance Dashboard</h2>
            <p class="text-muted">Manage and track student attendance for your classes</p>
        </div>
    </div>

    <div class="alert alert-warning" role="alert">
        Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.
    </div>

    <!-- Today's Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Students</h5>
                    <p class="card-text display-4">{{ $todaySummary['total_students'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Present</h5>
                    <p class="card-text display-4">{{ $todaySummary['present'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Absent</h5>
                    <p class="card-text display-4">{{ $todaySummary['absent'] ?? 0 }}</p>
                </div>
            </div>
        </div>
                                <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Attendance Rate</h5>
                    <p class="card-text display-4">{{ $todaySummary['attendance_rate'] ?? 0 }}%</p>
                    <p class="text-light small mt-2 mb-0">Attendance credit policy: Present = 1, Late = 1, Half Day = 0.5, Absent = 0. Leave is legacy and gives 0 credit.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Class-wise Attendance -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Today's Class Attendance</h5>
                </div>
                <div class="card-body">
                    @if(count($classData) > 0)
                        @foreach($classData as $class)
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    {{ $class['class']->class_name }} - {{ $class['subject']->name ?? 'All Subjects' }}
                                </h6>
                                <span class="badge bg-secondary">
                                    {{ $class['summary']['attendance_rate'] }}% Attendance
                                </span>
                            </div>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" 
                                     style="width: {{ $class['summary']['attendance_rate'] }}%">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <button class="btn btn-secondary btn-sm" type="button" disabled>
                                        <i class="bi bi-calendar-check"></i> Mark Attendance Disabled
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="text-muted d-block mb-1">
                                        {{ $class['summary']['present'] ?? 0 }} Present | 
                                        {{ $class['summary']['absent'] ?? 0 }} Absent
                                    </span>
                                    <span class="text-muted small">
                                        Late Days: {{ $class['summary']['late_days'] ?? '0' }} |
                                        Half Days: {{ $class['summary']['half_days'] ?? '0' }} |
                                        Attendance Credit: {{ $class['summary']['attendance_credit'] ?? 'N/A' }} |
                                        Leave Days: {{ $class['summary']['leave_days'] ?? '0' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="mt-3">No classes assigned for attendance marking today.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Low Attendance Alerts -->
    @if(count($lowAttendanceAlerts) > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle"></i> Low Attendance Alerts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Attendance Rate</th>
                                    <th>Absent Days</th>
                                    <th>Late Days</th>
                                    <th>Half Days</th>
                                    <th>Credit</th>
                                    <th>Leave Days</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lowAttendanceAlerts as $alert)
                                <tr>
                                    <td>{{ $alert['student']->name }}</td>
                                    <td>{{ $alert['student']->schoolClass->class_name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $alert['attendance_rate'] < 60 ? 'danger' : ($alert['attendance_rate'] < 75 ? 'warning' : 'success') }}">
                                            {{ $alert['attendance_rate'] }}%
                                        </span>
                                    </td>
                                    <td>{{ $alert['absent_days'] ?? '0' }}</td>
                                    <td>{{ $alert['late_days'] ?? '0' }}</td>
                                    <td>{{ $alert['half_days'] ?? '0' }}</td>
                                    <td>{{ $alert['attendance_credit'] ?? 'N/A' }}</td>
                                    <td>{{ $alert['leave_days'] ?? '0' }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                                            <i class="bi bi-eye"></i> Details Disabled
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up text-primary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">Attendance Reports</h6>
                    <p class="text-muted small">Teacher attendance reports are temporarily unavailable.</p>
                    <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                        Reports Disabled
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-spreadsheet text-success" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">Export Data</h6>
                    <p class="text-muted small">Teacher attendance export is not enabled yet.</p>
                    <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                        Export Disabled
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-bell text-info" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">Notifications</h6>
                    <p class="text-muted small">Manage attendance notifications</p>
                    <button class="btn btn-outline-info btn-sm" onclick="manageNotifications()">
                        Notification Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function manageNotifications() {
    // Implementation for notification settings
    alert('Notification settings functionality to be implemented');
}
</script>
@endsection
