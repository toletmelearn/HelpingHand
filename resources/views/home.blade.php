@extends('layouts.public')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Dashboard</h1>
        </div>
    </div>
    
    <!-- System Overview Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalStudents }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalTeachers }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Class Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $activeClassTeachers }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Today's Changes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $todayAuditLogs }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-history fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Core Management Areas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Core Management Systems</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Student Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-students'))
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
                        @endif
                        
                        <!-- Teacher Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-teachers'))
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-person-badge text-info"></i> Teacher Management</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.teachers.index') }}" class="btn btn-sm btn-outline-info mb-2 d-block">All Teachers</a>
                                        <a href="{{ route('admin.teachers.create') }}" class="btn btn-sm btn-outline-info d-block">Add Teacher</a>
                                        <a href="{{ url('/teachers-dashboard') }}" class="btn btn-sm btn-outline-info d-block">Teacher Dashboard</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- User & Access Management -->
                        @if(Auth::user()->hasRole('admin'))
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
                        @endif
                        
                        <!-- Attendance Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-attendance'))
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
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Academic & Examination Management -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Academic & Examination Management</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Academic Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-syllabi') || Auth::user()->hasPermission('view-daily-teaching-work'))
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
                        @endif
                        
                        <!-- Exam Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-exams'))
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
                        @endif
                        
                        <!-- Admit Card Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-admit-cards'))
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
                        @endif
                        
                        <!-- Exam Templates -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-exam-paper-templates'))
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
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Class & Schedule Management -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Class & Schedule Management</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Classes & Subjects -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-classes') || Auth::user()->hasPermission('view-subjects'))
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
                        @endif
                        
                        <!-- Time Management -->
                        @if(Auth::user()->hasRole('admin'))
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
                        @endif
                        
                        <!-- Staff Management -->
                        @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-teachers'))
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
                        @endif
                        
                        <!-- Class Teacher Control -->
                        @if(Auth::user()->hasRole('admin'))
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
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Audit & Compliance Dashboard -->
    @if(Auth::user()->hasRole('admin'))
    <div class="row">
        <!-- Most Edited Records -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Most Edited Records</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th>ID</th>
                                    <th>Edits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mostEditedRecords as $record)
                                    <tr>
                                        <td>{{ ucfirst($record->model_type) }}</td>
                                        <td>{{ $record->model_id }}</td>
                                        <td>{{ $record->count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Editing Users -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Top Editing Users</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Type</th>
                                    <th>Edits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topEditingUsers as $user)
                                    <tr>
                                        <td>{{ $user->user_id }}</td>
                                        <td>{{ ucfirst($user->user_type) }}</td>
                                        <td>{{ $user->count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Field Permissions -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Field Permissions</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="text-gray-800">{{ $totalFieldPermissions }}</h3>
                        <p class="text-muted">Total Permissions Configured</p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-shield-alt"></i> Manage Permissions
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-history"></i> View Audit Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Quick Actions -->
    @if(Auth::user()->hasRole('admin'))
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.class-teacher-assignments.index') }}" class="btn btn-primary w-100">
                                <i class="fas fa-chalkboard-teacher"></i><br>
                                Class Teachers
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-success w-100">
                                <i class="fas fa-shield-alt"></i><br>
                                Permissions
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-info w-100">
                                <i class="fas fa-history"></i><br>
                                Audit Logs
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.bell-schedules.index') }}" class="btn btn-warning w-100">
                                <i class="fas fa-bell"></i><br>
                                Bell Schedules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Assigned Admission Enquiries for Counsellor -->
    @if(isset($assignedEnquiries) && $assignedEnquiries->count() > 0)
    <div class="row mt-4 mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 bg-info text-white d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-comments me-2"></i> My Assigned Admission Enquiries</h6>
                    <span class="badge bg-light text-dark fw-bold">{{ $assignedEnquiries->count() }} active</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Candidate Name</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th>Follow-Up Date</th>
                                    <th>Last Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignedEnquiries as $enquiry)
                                    <tr>
                                        <td class="fw-bold">{{ $enquiry->candidate_name }}</td>
                                        <td>{{ $enquiry->parent_name }} ({{ $enquiry->phone }})</td>
                                        <td>
                                            @if($enquiry->status === 'new')
                                                <span class="badge bg-info">New</span>
                                            @elseif($enquiry->status === 'interested')
                                                <span class="badge bg-success">Interested</span>
                                            @elseif($enquiry->status === 'follow_up')
                                                <span class="badge bg-warning text-dark">Follow-Up</span>
                                            @elseif($enquiry->status === 'admitted')
                                                <span class="badge bg-primary">Admitted</span>
                                            @elseif($enquiry->status === 'closed')
                                                <span class="badge bg-secondary">Closed</span>
                                            @else
                                                <span class="badge bg-secondary text-capitalize">{{ $enquiry->status }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($enquiry->follow_up_date)
                                                <i class="far fa-calendar-alt text-muted me-1"></i> {{ $enquiry->follow_up_date->format('Y-m-d') }}
                                            @else
                                                <span class="text-muted small">Not Scheduled</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ Str::limit($enquiry->remarks ?: ($enquiry->follow_up_notes ?: 'N/A'), 80) }}</span>
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
</div>
@endsection
