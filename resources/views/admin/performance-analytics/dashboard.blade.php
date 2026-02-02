@extends('layouts.admin')

@section('title', 'Performance Analytics Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-bar-chart-line"></i> Performance Analytics Dashboard
        </h1>
        <div>
            <a href="{{ route('admin.performance-analytics.index') }}" class="btn btn-outline-primary me-2">
                <i class="bi bi-list"></i> Analytics Reports
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('admin.performance-analytics.export', ['format' => 'pdf']) }}">PDF Report</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.performance-analytics.export', ['format' => 'excel']) }}">Excel Sheet</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.performance-analytics.export', ['format' => 'csv']) }}">CSV File</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.performance-analytics.dashboard') }}" class="row g-3">
                <div class="col-md-5">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="{{ $startDate }}" max="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-5">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="{{ $endDate }}" max="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overall Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['total_users'] }}</h3>
                            <p class="card-text">Total Users</p>
                        </div>
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['active_users'] }}</h3>
                            <p class="card-text">Active Users</p>
                        </div>
                        <i class="bi bi-person-check fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['active_user_percentage'] }}%</h3>
                            <p class="card-text">Engagement Rate</p>
                        </div>
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['total_students'] }}</h3>
                            <p class="card-text">Students</p>
                        </div>
                        <i class="bi bi-person-lines-fill fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['total_teachers'] }}</h3>
                            <p class="card-text">Teachers</p>
                        </div>
                        <i class="bi bi-person-badge fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $overallStats['total_modules'] }}</h3>
                            <p class="card-text">Modules</p>
                        </div>
                        <i class="bi bi-grid fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Frequency Section -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Top Login Frequencies</h5>
                </div>
                <div class="card-body">
                    @if($loginFrequency->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Login Count</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loginFrequency as $user)
                                        <tr>
                                            <td>
                                                <strong>{{ $user->name }}</strong>
                                                <div class="small text-muted">{{ $user->email }}</div>
                                            </td>
                                            <td><span class="badge bg-primary">{{ $user->login_count }}</span></td>
                                            <td>
                                                @if($user->hasRole('admin'))
                                                    <span class="badge bg-danger">Admin</span>
                                                @elseif($user->hasRole('teacher'))
                                                    <span class="badge bg-info">Teacher</span>
                                                @elseif($user->hasRole('accountant'))
                                                    <span class="badge bg-success">Accountant</span>
                                                @else
                                                    <span class="badge bg-secondary">User</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No login data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Module Usage -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-app"></i> Module Usage Distribution</h5>
                </div>
                <div class="card-body">
                    @if($moduleUsage->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Access Count</th>
                                        <th>Popularity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($moduleUsage as $module)
                                        <tr>
                                            <td>
                                                <strong>{{ ucfirst(str_replace('_', ' ', $module->module_accessed)) }}</strong>
                                            </td>
                                            <td><span class="badge bg-info">{{ $module->usage_count }}</span></td>
                                            <td>
                                                @php
                                                    $maxCount = $moduleUsage->max('usage_count');
                                                    $percentage = $maxCount > 0 ? min(100, ($module->usage_count / $maxCount) * 100) : 0;
                                                @endphp
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentage }}%;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No module usage data available for the selected period.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Trends -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Student Academic Trends</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Total Students</h6>
                            <p class="display-6 text-primary">{{ $studentAcademicTrends['total_students'] }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Avg Attendance</h6>
                            <p class="display-6 text-success">{{ $studentAcademicTrends['average_attendance'] }} days</p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Pass Rate</h6>
                        <p class="display-6 text-info">{{ $studentAcademicTrends['pass_rate'] }}%</p>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Grade Distribution</h6>
                        <div class="row">
                            @foreach($studentAcademicTrends['grade_distribution'] as $grade => $count)
                                <div class="col-4">
                                    <span class="badge bg-secondary">{{ $grade }}: {{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Patterns -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Attendance Patterns</h5>
                </div>
                <div class="card-body">
                    <h6>Overall Attendance Rate</h6>
                    <p class="display-6 text-success">{{ $attendancePatterns['overall_attendance_rate'] }}%</p>
                    
                    <div class="mt-3">
                        <h6>Daily Attendance Pattern</h6>
                        @if($attendancePatterns['daily_pattern']->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attendancePatterns['daily_pattern']->take(5) as $day)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</td>
                                                <td>{{ $day->total_attendance }}</td>
                                                <td><span class="text-success">{{ $day->present_count }}</span></td>
                                                <td><span class="text-danger">{{ $day->absent_count }}</span></td>
                                                <td><span class="text-warning">{{ $day->late_count }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted">No attendance data available for the selected period.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Compliance -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-check"></i> Teacher Compliance</h5>
        </div>
        <div class="card-body">
            @if(count($teacherCompliance) > 0)
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Compliance Rate</th>
                                <th>Total Days</th>
                                <th>On-Time Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teacherCompliance->take(10) as $tc)
                                <tr>
                                    <td><strong>{{ $tc['name'] }}</strong></td>
                                    <td>
                                        @php
                                            $rate = $tc['compliance_rate'];
                                        @endphp
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">{{ $rate }}%</span>
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $rate }}%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $tc['total_days'] }}</td>
                                    <td>{{ $tc['on_time_days'] }}</td>
                                    <td>
                                        @if($rate >= 95)
                                            <span class="badge bg-success">Excellent</span>
                                        @elseif($rate >= 85)
                                            <span class="badge bg-info">Good</span>
                                        @elseif($rate >= 70)
                                            <span class="badge bg-warning">Fair</span>
                                        @else
                                            <span class="badge bg-danger">Poor</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">No teacher compliance data available for the selected period.</p>
            @endif
        </div>
    </div>

    <!-- Export Options -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-down"></i> Export Performance Data</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-file-pdf fs-1 text-danger"></i>
                        <h6>PDF Report</h6>
                        <p class="text-muted small">Download comprehensive performance report in PDF format</p>
                        <a href="{{ route('admin.performance-analytics.export', ['format' => 'pdf', 'start_date' => $startDate, 'end_date' => $endDate]) }}" 
                           class="btn btn-sm btn-outline-danger">Download</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-file-excel fs-1 text-success"></i>
                        <h6>Excel Sheet</h6>
                        <p class="text-muted small">Export data in Excel format for detailed analysis</p>
                        <a href="{{ route('admin.performance-analytics.export', ['format' => 'excel', 'start_date' => $startDate, 'end_date' => $endDate]) }}" 
                           class="btn btn-sm btn-outline-success">Download</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-file-csv fs-1 text-info"></i>
                        <h6>CSV File</h6>
                        <p class="text-muted small">Export raw data in CSV format</p>
                        <a href="{{ route('admin.performance-analytics.export', ['format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate]) }}" 
                           class="btn btn-sm btn-outline-info">Download</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart initialization would go here if needed
</script>
@endpush
@endsection
