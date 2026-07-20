@extends('layouts.admin')

@section('title', 'Performance Analytics')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-graph-up-arrow"></i> Performance Analytics Dashboard</h1>
        <div>
            <a href="{{ route('admin.performance-analytics.export', 'pdf') }}?start_date={{ $startDate }}&end_date={{ $endDate }}" class="btn btn-danger">
                <i class="bi bi-file-pdf"></i> Export PDF
            </a>
            <a href="{{ route('admin.performance-analytics.export', 'excel') }}?start_date={{ $startDate }}&end_date={{ $endDate }}" class="btn btn-success">
                <i class="bi bi-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.performance-analytics.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Users</h6>
                    <h2>{{ $overallStats['total_users'] }}</h2>
                    <small><i class="bi bi-check-circle"></i> {{ $overallStats['active_users'] }} active</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Students</h6>
                    <h2>{{ $overallStats['total_students'] }}</h2>
                    <small><i class="bi bi-people"></i> Active in system</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Teachers</h6>
                    <h2>{{ $overallStats['total_teachers'] }}</h2>
                    <small><i class="bi bi-person-badge"></i> Staff members</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Active Users %</h6>
                    <h2>{{ $overallStats['active_user_percentage'] }}%</h2>
                    <small><i class="bi bi-activity"></i> Engagement rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Top 10 Login Frequency</h5>
                </div>
                <div class="card-body">
                    <canvas id="loginFrequencyChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Module Usage Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="moduleUsageChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Compliance -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Teacher Compliance Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Attendance Rate</th>
                                    <th>Lesson Plans Submitted</th>
                                    <th>On-Time Arrival</th>
                                    <th>Overall Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teacherCompliance as $teacher)
                                <tr>
                                    <td>{{ $teacher->name }}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: {{ $teacher->attendance_rate ?? 0 }}%">
                                                {{ $teacher->attendance_rate ?? 0 }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $teacher->lesson_plans ?? 0 }}</td>
                                    <td>{{ $teacher->on_time_percentage ?? 0 }}%</td>
                                    <td>
                                        <span class="badge bg-{{ $teacher->overall_score >= 80 ? 'success' : ($teacher->overall_score >= 60 ? 'warning' : 'danger') }}">
                                            {{ $teacher->overall_score ?? 0 }}%
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No data available for selected period</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Academic Trends -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Student Academic Performance Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="academicTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Patterns -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Attendance Patterns</h5>
                </div>
                <div class="card-body">
                    <canvas id="attendancePatternsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Login Frequency Chart
    const loginCtx = document.getElementById('loginFrequencyChart').getContext('2d');
    new Chart(loginCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($loginFrequency->pluck('name')) !!},
            datasets: [{
                label: 'Login Count',
                data: {!! json_encode($loginFrequency->pluck('login_count')) !!},
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Module Usage Chart
    const moduleCtx = document.getElementById('moduleUsageChart').getContext('2d');
    new Chart(moduleCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($moduleUsage->pluck('module_accessed')) !!},
            datasets: [{
                data: {!! json_encode($moduleUsage->pluck('usage_count')) !!},
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });

    // Academic Trends Chart
    const trendsCtx = document.getElementById('academicTrendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($studentAcademicTrends->pluck('period')) !!},
            datasets: [{
                label: 'Average Score',
                data: {!! json_encode($studentAcademicTrends->pluck('average_score')) !!},
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Attendance Patterns Chart
    const attendanceCtx = document.getElementById('attendancePatternsChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($attendancePatterns->pluck('date')) !!},
            datasets: [{
                label: 'Attendance Rate (%)',
                data: {!! json_encode($attendancePatterns->pluck('attendance_rate')) !!},
                borderColor: 'rgba(153, 102, 255, 1)',
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
</script>
@endpush
@endsection
