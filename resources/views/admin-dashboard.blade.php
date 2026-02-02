@extends('layouts.admin')

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
                                        @if(Route::has('users.index'))
                                            <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Manage Users</a>
                                        @endif
                                        @if(Route::has('admin.role-permissions.index'))
                                            <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-sm btn-outline-primary d-block">Manage Roles</a>
                                        @endif
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
                        
                        <!-- Analytics Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-bar-chart-line text-info"></i> Analytics</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.analytics.index'))
                                            <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Analytics Dashboard</a>
                                        @endif
                                        @if(Route::has('admin.performance.index'))
                                            <a href="{{ route('admin.performance.index') }}" class="btn btn-sm btn-outline-info d-block">Performance Metrics</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Biometric System -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-fingerprint text-warning"></i> Biometric System</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.teacher-biometrics.index'))
                                            <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Teacher Records</a>
                                        @endif
                                        @if(Route::has('admin.biometric-devices.index'))
                                            <a href="{{ route('admin.biometric-devices.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Device Management</a>
                                        @endif
                                        @if(Route::has('admin.sync-monitor.index'))
                                            <a href="{{ route('admin.sync-monitor.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Sync Monitor</a>
                                        @endif
                                        @if(Route::has('admin.teacher-biometrics.reports'))
                                            <a href="{{ route('admin.teacher-biometrics.reports') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Working Hours Reports</a>
                                        @endif
                                        @if(Route::has('admin.analytics.late-arrivals'))
                                            <a href="{{ route('admin.analytics.late-arrivals') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Late Arrival Reports</a>
                                        @endif
                                        @if(Route::has('admin.analytics.early-departures'))
                                            <a href="{{ route('admin.analytics.early-departures') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Early Departure Reports</a>
                                        @endif
                                        @if(Route::has('admin.teacher-biometrics.settings'))
                                            <a href="{{ route('admin.teacher-biometrics.settings') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">System Settings</a>
                                        @endif
                                        @if(Route::has('admin.teacher-biometrics.export'))
                                            <a href="{{ route('admin.teacher-biometrics.export') }}" class="btn btn-sm btn-outline-warning d-block">PDF/Excel Export</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fee Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-currency-dollar text-success"></i> Fee Management</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.fee-structures.index'))
                                            <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Fee Structures</a>
                                        @endif
                                        @if(Route::has('admin.fees.index'))
                                            <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Fee Collection</a>
                                        @endif
                                        @if(Route::has('admin.fees.index'))
                                            <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Pending Dues</a>
                                        @endif
                                        @if(Route::has('admin.fees.index'))
                                            <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-success d-block">Comprehensive Reports</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Advanced Admin Config -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-gear text-info"></i> Advanced Config</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.grading-systems.index'))
                                            <a href="{{ route('admin.grading-systems.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Grading Systems</a>
                                        @endif
                                        @if(Route::has('admin.result-formats.index'))
                                            <a href="{{ route('admin.result-formats.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Result Formats</a>
                                        @endif
                                        @if(Route::has('admin.examination-patterns.index'))
                                            <a href="{{ route('admin.examination-patterns.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Exam Patterns</a>
                                        @endif
                                        @if(Route::has('admin.permissions.index'))
                                            <a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Permissions</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Audit & Security -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-shield-lock text-primary"></i> Audit & Security</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.audit-logs.index'))
                                            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Audit Logs</a>
                                        @endif
                                        @if(Route::has('admin.field-permissions.index'))
                                            <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Activity Logs</a>
                                        @endif
                                        @if(Route::has('admin.role-permissions.index'))
                                            <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Permission Logs</a>
                                        @endif
                                        @if(Route::has('admin.analytics.index'))
                                            <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-primary d-block">Security Monitoring</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports & Analytics -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-bar-chart text-danger"></i> Reports & Analytics</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.reports.index'))
                                            <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">General Reports</a>
                                        @endif
                                        @if(Route::has('attendance.reports'))
                                            <a href="{{ route('attendance.reports') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Attendance Reports</a>
                                        @endif
                                        @if(Route::has('admin.budget.reports'))
                                            <a href="{{ route('admin.budget.reports') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Budget Reports</a>
                                        @endif
                                        @if(Route::has('admin.performance.index'))
                                            <a href="{{ route('admin.performance.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Performance Reports</a>
                                        @endif
                                        @if(Route::has('admin.advanced-reports.dashboard'))
                                            <a href="{{ route('admin.advanced-reports.dashboard') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Advanced Dashboard</a>
                                        @endif
                                        @if(Route::has('admin.languages.index'))
                                            <a href="{{ route('admin.languages.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Language Management</a>
                                        @endif
                                        @if(Route::has('admin.notification-settings.index'))
                                            <a href="{{ route('admin.notification-settings.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Notification Settings</a>
                                        @endif
                                        @if(Route::has('admin.performance-analytics.dashboard'))
                                            <a href="{{ route('admin.performance-analytics.dashboard') }}" class="btn btn-sm btn-outline-danger d-block">Performance Analytics</a>
                                        @endif
                                        @if(Route::has('admin.analytics.index'))
                                            <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-danger d-block">Analytics Dashboard</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- System Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-tools text-dark"></i> System Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.language-settings.index') }}" class="btn btn-sm btn-outline-dark mb-2 d-block">Language Settings</a>
                                        <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-outline-dark d-block">Notifications</a>
                                        <a href="{{ route('admin.backups.index') }}" class="btn btn-sm btn-outline-dark d-block">Backups</a>
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
                        
                        <!-- Lesson Plan Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-journal-bookmark text-primary"></i> Lesson Plans</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.lesson-plans.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">All Lesson Plans</a>
                                        <a href="{{ route('admin.lesson-plans.compliance') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Teacher Compliance</a>
                                        <a href="{{ route('admin.lesson-plans.subject-progress') }}" class="btn btn-sm btn-outline-primary d-block">Subject Progress</a>
                                        <a href="{{ route('admin.lesson-plans.export-pdf') }}" class="btn btn-sm btn-outline-primary d-block">Export PDF</a>
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
                        <!-- Budget Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-wallet text-warning"></i> Budget Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.budgets.index') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Budget Settings</a>
                                        <a href="{{ route('admin.expenses.index') }}" class="btn btn-sm btn-outline-warning d-block">Expense Tracking</a>
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
                                        <a href="{{ route('admin.assets.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Assets</a>
                                        <a href="{{ route('admin.inventory.index') }}" class="btn btn-sm btn-outline-danger d-block">Equipment</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Certificate Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-award text-success"></i> Certificates</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.certificates.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Certificates</a>
                                        <a href="{{ route('admin.certificate-templates.index') }}" class="btn btn-sm btn-outline-success d-block">Templates</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Library Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-book text-info"></i> Library</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.books.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Books Management</a>
                                        <a href="{{ route('admin.book-issues.index') }}" class="btn btn-sm btn-outline-info d-block">Issue Management</a>
                                        <a href="{{ route('admin.library.dashboard') }}" class="btn btn-sm btn-outline-info d-block">Library Dashboard</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Advanced Admin Config -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-gear text-info"></i> Advanced Config</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.configurations.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">System Config</a>
                                        <a href="{{ route('admin.grading-systems.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Grading Systems</a>
                                        <a href="{{ route('admin.result-formats.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Result Formats</a>
                                        <a href="{{ route('admin.examination-patterns.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Exam Patterns</a>
                                        <a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Permissions</a>
                                        <a href="{{ route('admin.document-formats.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Document Formats</a>
                                        <a href="{{ route('admin.student-statuses.index') }}" class="btn btn-sm btn-outline-info d-block">Student Statuses</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports & Analytics -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-bar-chart text-danger"></i> Reports & Analytics</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">General Reports</a>
                                        <a href="{{ route('admin.budget.reports') }}" class="btn btn-sm btn-outline-danger d-block">Budget Reports</a>
                                        <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm btn-outline-danger d-block">Analytics</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-tools text-dark"></i> System Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.language-settings.index') }}" class="btn btn-sm btn-outline-dark mb-2 d-block">Language Settings</a>
                                        <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-outline-dark d-block">Notifications</a>
                                        <a href="{{ route('admin.backups.index') }}" class="btn btn-sm btn-outline-dark d-block">Backups</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assignment Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-link-45deg text-primary"></i> Assignments</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.teacher-subject-assignments.index'))
                                            <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Teacher-Subject</a>
                                        @endif
                                        @if(Route::has('admin.teacher-class-assignments.index'))
                                            <a href="{{ route('admin.teacher-class-assignments.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">Teacher-Class</a>
                                        @endif
                                        <a href="#" class="btn btn-sm btn-outline-primary d-block">Subject-Class</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Student Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-mortarboard text-success"></i> Student Admin</h6>
                                    <div class="mt-3">
                                        @if(Route::has('admin.student-promotions.index'))
                                            <a href="{{ route('admin.student-promotions.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">Student Promotion</a>
                                        @endif
                                        @if(Route::has('admin.student-statuses.index'))
                                            <a href="{{ route('admin.student-statuses.index') }}" class="btn btn-sm btn-outline-success d-block">Status Management</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enhanced Reports -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-bar-chart-line text-warning"></i> Detailed Reports</h6>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-warning mb-2 d-block">Fee Reports</a>
                                        <a href="#" class="btn btn-sm btn-outline-warning mb-2 d-block">Salary Reports</a>
                                        <a href="#" class="btn btn-sm btn-outline-warning d-block">Performance Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Document Management -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-file-richtext text-info"></i> Documents</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.result-formats.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Result Formats</a>
                                        <a href="{{ route('admin.document-formats.index') }}" class="btn btn-sm btn-outline-info d-block">Custom Templates</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Add Student -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-person-plus text-success"></i> Add Student</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('students.create') }}" class="btn btn-sm btn-outline-success d-block">New Student</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mark Attendance -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-calendar-check text-info"></i> Mark Attendance</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('attendance.create') }}" class="btn btn-sm btn-outline-info d-block">Daily Attendance</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Generate Report -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-file-earmark-bar-graph text-warning"></i> Generate Report</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('attendance.reports') }}" class="btn btn-sm btn-outline-warning d-block">Attendance Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Search -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-search text-primary"></i> Quick Search</h6>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-primary d-block">Search Records</a>
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
