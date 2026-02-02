@extends('layouts.admin')

@section('title', 'Advanced Reporting Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-graph-up"></i> Advanced Reporting Dashboard
        </h1>
        <div>
            <a href="{{ route('admin.advanced-reports.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-list"></i> Manage Reports
            </a>
            <a href="{{ route('admin.advanced-reports.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Report
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Options</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.advanced-reports.dashboard') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="academic_session_id" class="form-label">Academic Session</label>
                    <select name="academic_session_id" id="academic_session_id" class="form-select">
                        <option value="">All Sessions</option>
                        @foreach($academicSessions as $session)
                            <option value="{{ $session->id }}" {{ $academicSessionId == $session->id ? 'selected' : '' }}>
                                {{ $session->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="class_id" class="form-label">Class</label>
                    <select name="class_id" id="class_id" class="form-select">
                        <option value="">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ $classId == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="section_id" class="form-label">Section</label>
                    <select name="section_id" id="section_id" class="form-select">
                        <option value="">All Sections</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}" {{ $sectionId == $section->id ? 'selected' : '' }}>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_range" class="form-label">Date Range</label>
                    <select name="date_range" id="date_range" class="form-select">
                        <option value="today" {{ $dateRange == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_week" {{ $dateRange == 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_year" {{ $dateRange == 'this_year' ? 'selected' : '' }}>This Year</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards Row 1 -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $studentStats['total_students'] }}</h3>
                            <p class="card-text">Total Students</p>
                        </div>
                        <i class="bi bi-people fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-arrow-up-circle"></i> 
                            {{ $studentStats['new_admissions'] }} new admissions
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">â‚¹{{ number_format($feeStats['total_fees_collected']) }}</h3>
                            <p class="card-text">Fees Collected</p>
                        </div>
                        <i class="bi bi-currency-rupee fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-exclamation-triangle"></i> 
                            â‚¹{{ number_format($feeStats['pending_dues']) }} pending
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $attendanceStats['attendance_rate'] }}%</h3>
                            <p class="card-text">Attendance Rate</p>
                        </div>
                        <i class="bi bi-calendar-check fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-person-x"></i> 
                            {{ $attendanceStats['absent_count'] }} absences
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $examStats['total_exams'] }}</h3>
                            <p class="card-text">Total Exams</p>
                        </div>
                        <i class="bi bi-file-earmark-text fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-clock"></i> 
                            {{ $examStats['upcoming_exams'] }} upcoming
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row 2 -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $libraryStats['available_books'] }}</h3>
                            <p class="card-text">Available Books</p>
                        </div>
                        <i class="bi bi-book fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-arrow-repeat"></i> 
                            {{ $libraryStats['books_issued_this_period'] }} issued
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $libraryStats['overdue_books'] }}</h3>
                            <p class="card-text">Overdue Books</p>
                        </div>
                        <i class="bi bi-exclamation-octagon fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-clock"></i> 
                            Requires attention
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $biometricStats['attendance_rate'] }}%</h3>
                            <p class="card-text">Teacher Attendance</p>
                        </div>
                        <i class="bi bi-fingerprint fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-person"></i> 
                            {{ $biometricStats['total_teacher_records'] }} records
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ $studentStats['active_students'] }}</h3>
                            <p class="card-text">Active Students</p>
                        </div>
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                    <div class="mt-2">
                        <small>
                            <i class="bi bi-x-circle"></i> 
                            {{ $studentStats['passed_out'] + $studentStats['left_school'] }} inactive
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts Section -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Fee Collection Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="feeChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Student Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="studentStatusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-speedometer2"></i> Quick Metrics</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Late Arrivals
                            <span class="badge bg-warning rounded-pill">{{ $attendanceStats['late_arrivals'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Exam Results Published
                            <span class="badge bg-success rounded-pill">{{ $examStats['results_published'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Teachers on Time
                            <span class="badge bg-success rounded-pill">{{ $biometricStats['on_time_arrivals'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Completed Exams
                            <span class="badge bg-info rounded-pill">{{ $examStats['completed_exams'] }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Summary Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Metric</th>
                                    <th>Students</th>
                                    <th>Fees</th>
                                    <th>Attendance</th>
                                    <th>Exams</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Current Period</strong></td>
                                    <td>{{ $studentStats['new_admissions'] }}</td>
                                    <td>â‚¹{{ number_format($feeStats['total_fees_collected']) }}</td>
                                    <td>{{ $attendanceStats['attendance_rate'] }}%</td>
                                    <td>{{ $examStats['total_exams'] }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <td>{{ $studentStats['total_students'] }}</td>
                                    <td>â‚¹{{ number_format($feeStats['total_fees_collected'] + $feeStats['pending_dues']) }}</td>
                                    <td>{{ $attendanceStats['total_attendance'] }}</td>
                                    <td>{{ Exam::count() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Fee Collection Chart
    const feeCtx = document.getElementById('feeChart').getContext('2d');
    const feeChart = new Chart(feeCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Fees Collected (â‚¹)',
                data: [120000, 190000, 150000, 180000, 220000, 170000],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Student Status Chart
    const studentCtx = document.getElementById('studentStatusChart').getContext('2d');
    const studentChart = new Chart(studentCtx, {
        type: 'pie',
        data: {
            labels: ['Active', 'Passed Out', 'Left School'],
            datasets: [{
                data: [{{ $studentStats['active_students'] }}, {{ $studentStats['passed_out'] }}, {{ $studentStats['left_school'] }}],
                backgroundColor: [
                    'rgb(75, 192, 192)',
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
</script>
@endpush
