@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Administrator Dashboard</h1>
            <p>Welcome to the Administrator Dashboard, {{ Auth::user()->name }}!</p>
        </div>
    </div>
    
    <!-- System Overview Stats -->
    <div class="row mt-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Students</h5>
                    <p class="card-text display-4">{{ $totalStudents }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Teachers</h5>
                    <p class="card-text display-4">{{ $totalTeachers }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Users</h5>
                    <p class="card-text display-4">{{ $totalUsers }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Attendance</h5>
                    <p class="card-text display-4">{{ $totalAttendance }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Exam Papers</h5>
                    <p class="card-text display-4">{{ $totalExamPapers }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Bell Timings</h5>
                    <p class="card-text display-4">{{ $totalBellTimings }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Core Management Areas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Core Management Systems</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Student Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-people-fill text-success"></i> Student Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">All Students</a>
                                        <a href="{{ route('students.create') }}" class="btn btn-sm btn-outline-success d-block">Add Student</a>
                                        <a href="{{ url('/students-dashboard') }}" class="btn btn-sm btn-outline-success d-block">Student Dashboard</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-person-badge text-info"></i> Teacher Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('teachers.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">All Teachers</a>
                                        <a href="{{ route('teachers.create') }}" class="btn btn-sm btn-outline-info d-block">Add Teacher</a>
                                        <a href="{{ url('/teachers-dashboard') }}" class="btn btn-sm btn-outline-info d-block">Teacher Dashboard</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User & Access Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-people text-primary"></i> User Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Manage Users</a>
                                        <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-sm btn-outline-primary d-block">Manage Roles</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-calendar-check text-success"></i> Attendance</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Attendance Records</a>
                                        <a href="{{ route('attendance.create') }}" class="btn btn-sm btn-outline-success d-block">Mark Attendance</a>
                                        <a href="{{ route('attendance.reports') }}" class="btn btn-sm btn-outline-success d-block">Attendance Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Academic & Examination Management -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Academic & Examination Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Academic Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-mortarboard text-warning"></i> Academic Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.syllabi.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Syllabus</a>
                                        <a href="{{ route('admin.daily-teaching-work.index') }}" class="btn btn-sm btn-outline-warning d-block">Daily Teaching</a>
                                        <a href="{{ route('admin.syllabi.progress-report') }}" class="btn btn-sm btn-outline-warning d-block">Progress Report</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Exam Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-journal-text text-danger"></i> Exam Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Exam Papers</a>
                                        <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-danger d-block">Exams</a>
                                        <a href="{{ route('admin.results.index') }}" class="btn btn-sm btn-outline-danger d-block">Results</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Admit Card Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-card-text text-info"></i> Admit Cards</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.admit-cards.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Manage Admit Cards</a>
                                        <a href="{{ route('admin.admit-card-formats.index') }}" class="btn btn-sm btn-outline-info d-block">Card Formats</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Exam Templates -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-file-earmark-text text-primary"></i> Exam Templates</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.exam-paper-templates.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Templates</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Class & Schedule Management -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Class & Schedule Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Classes & Subjects -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-stack text-info"></i> Classes & Subjects</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.sections.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Sections</a>
                                        <a href="{{ route('admin.subjects.index') }}" class="btn btn-sm btn-outline-info d-block">Subjects</a>
                                        <a href="{{ route('admin.academic-sessions.index') }}" class="btn btn-sm btn-outline-info d-block">Sessions</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-alarm text-warning"></i> Time Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.bell-schedules.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Bell Schedules</a>
                                        <a href="{{ route('admin.special-day-overrides.index') }}" class="btn btn-sm btn-outline-warning d-block">Special Days</a>
                                        <a href="{{ route('admin.bell-schedules.live-monitor') }}" class="btn btn-sm btn-outline-warning d-block">Live Monitor</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Staff Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-exchange text-primary"></i> Staff Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.teacher-substitutions.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Substitutions</a>
                                        <a href="{{ route('admin.teacher-substitutions.today') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Today's Subs</a>
                                        <a href="{{ route('admin.teacher-substitutions.absence-overview') }}" class="btn btn-sm btn-outline-primary d-block">Absence Overview</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Class Teacher Control -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-chalkboard-teacher text-success"></i> Class Teachers</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.class-teacher-assignments.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Assignments</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Biometric Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-fingerprint text-primary"></i> Biometric System</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Biometric Dashboard</a>
                                        <a href="{{ route('admin.biometric-devices.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Device Management</a>
                                        <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-primary d-block">Analytics</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Financial & Inventory Management -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Financial & Inventory Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Fee Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-currency-dollar text-success"></i> Fee Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Fees</a>
                                        <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-sm btn-outline-success d-block">Fee Structures</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Budget Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-wallet text-warning"></i> Budget Management</h6>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-warning mb-2 d-block disabled">Budget Settings</a>
                                        <a href="#" class="btn btn-sm btn-outline-warning d-block disabled">Expense Tracking</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Library Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-book text-info"></i> Library Management</h6>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-info mb-2 d-block disabled">Books Catalog</a>
                                        <a href="#" class="btn btn-sm btn-outline-info d-block disabled">Issue/Return</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventory Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-box-seam text-danger"></i> Inventory</h6>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-danger mb-2 d-block disabled">Assets</a>
                                        <a href="#" class="btn btn-sm btn-outline-danger d-block disabled">Equipment</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Audit & Compliance -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Audit & Compliance</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Audit & Compliance -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-clipboard-data text-primary"></i> Audit & Compliance</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">
                                            <i class="bi bi-clipboard-data"></i> Audit Logs ({{ $todayAuditLogs }} today)
                                        </a>
                                        <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-sm btn-outline-info d-block">
                                            <i class="bi bi-shield-lock"></i> Field Permissions ({{ $totalFieldPermissions }})
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports & Analytics -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-bar-chart text-success"></i> Reports & Analytics</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('attendance.reports') }}" class="btn btn-sm btn-outline-success mb-2 d-block">
                                            <i class="bi bi-bar-chart-line"></i> Attendance Reports
                                        </a>
                                        <a href="{{ route('admin.results.index') }}" class="btn btn-sm btn-outline-success d-block">
                                            <i class="bi bi-award"></i> Results Management
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Configuration -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-gear text-warning"></i> System Configuration</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">
                                            <i class="bi bi-people"></i> Role Permissions
                                        </a>
                                        <a href="{{ route('admin.sections.index') }}" class="btn btn-sm btn-outline-warning d-block">
                                            <i class="bi bi-stack"></i> Class/Section Management
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Active Class Teachers</h6>
                            <p class="text-muted">{{ $activeClassTeachers }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6>Today's Changes</h6>
                            <p class="text-muted">{{ $todayAuditLogs }}</p>
                        </div>
                        <div class="col-md-4">
                            <h6>Locked Records</h6>
                            <p class="text-muted">{{ $lockedRecords }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection