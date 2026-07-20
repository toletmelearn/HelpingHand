@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            </h1>
            
            @if(isset($showOnboardingChecklist) && $showOnboardingChecklist)
            <!-- Onboarding Next Steps Checklist -->
            <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #eef2f3 0%, #d8e2dc 100%); border-left: 5px solid #0d6efd; border-radius: 12px;">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-route text-primary me-2"></i> Onboarding: Recommended Next Steps</h4>
                    <p class="text-muted mb-4">Congratulations on completing the School Setup Wizard! Please follow the steps below to populate your school environment.</p>
                    
                    <div class="row g-3">
                        <!-- Step 1: Teachers -->
                        <div class="col-md-4">
                            <div class="p-3 bg-white shadow-sm rounded-3 h-100 d-flex align-items-start">
                                @if($stats['total_teachers'] > 0)
                                    <div class="bg-success text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle"></i></div>
                                    <div>
                                        <strong class="d-block text-muted text-decoration-line-through">① Import Teachers</strong>
                                        <span class="badge bg-success-subtle text-success small mt-1">Configured ✓</span>
                                    </div>
                                @else
                                    <div class="bg-primary text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-chalkboard-teacher"></i></div>
                                    <div>
                                        <strong class="d-block text-dark">① Import Teachers</strong>
                                        <span class="text-muted d-block mb-2" style="font-size: 0.8rem;">Provision academic staff profiles.</span>
                                        <a href="{{ route('imports.wizard', ['module' => 'teachers']) }}" class="btn btn-sm btn-link p-0 text-primary fw-bold text-decoration-none">Launch Import Wizard <i class="fas fa-chevron-right ms-1"></i></a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Step 2: Students -->
                        <div class="col-md-4">
                            <div class="p-3 bg-white shadow-sm rounded-3 h-100 d-flex align-items-start">
                                @if($stats['total_students'] > 0)
                                    <div class="bg-success text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle"></i></div>
                                    <div>
                                        <strong class="d-block text-muted text-decoration-line-through">② Import Students</strong>
                                        <span class="badge bg-success-subtle text-success small mt-1">Configured ✓</span>
                                    </div>
                                @else
                                    <div class="bg-primary text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-graduation-cap"></i></div>
                                    <div>
                                        <strong class="d-block text-dark">② Import Students</strong>
                                        <span class="text-muted d-block mb-2" style="font-size: 0.8rem;">Populate active student directory.</span>
                                        <a href="{{ route('imports.wizard', ['module' => 'students']) }}" class="btn btn-sm btn-link p-0 text-primary fw-bold text-decoration-none">Launch Import Wizard <i class="fas fa-chevron-right ms-1"></i></a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Step 3: Parents -->
                        <div class="col-md-4">
                            <div class="p-3 bg-white shadow-sm rounded-3 h-100 d-flex align-items-start">
                                @if($stats['total_parents'] > 0)
                                    <div class="bg-success text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle"></i></div>
                                    <div>
                                        <strong class="d-block text-muted text-decoration-line-through">③ Import Parents</strong>
                                        <span class="badge bg-success-subtle text-success small mt-1">Configured ✓</span>
                                    </div>
                                @else
                                    <div class="bg-primary text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-users"></i></div>
                                    <div>
                                        <strong class="d-block text-dark">③ Import Parents</strong>
                                        <span class="text-muted d-block mb-2" style="font-size: 0.8rem;">Ingest parent & billing contacts.</span>
                                        <a href="{{ route('imports.wizard', ['module' => 'parents']) }}" class="btn btn-sm btn-link p-0 text-primary fw-bold text-decoration-none">Launch Import Wizard <i class="fas fa-chevron-right ms-1"></i></a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Step 4: Fee Structures -->
                        <div class="col-md-4">
                            <div class="p-3 bg-white shadow-sm rounded-3 h-100 d-flex align-items-start">
                                @if($stats['total_fee_structures'] > 0)
                                    <div class="bg-success text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check-circle"></i></div>
                                    <div>
                                        <strong class="d-block text-muted text-decoration-line-through">④ Configure Fee Structure</strong>
                                        <span class="badge bg-success-subtle text-success small mt-1">Configured ✓</span>
                                    </div>
                                @else
                                    <div class="bg-primary text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-file-invoice-dollar"></i></div>
                                    <div>
                                        <strong class="d-block text-dark">④ Configure Fee Structure</strong>
                                        <span class="text-muted d-block mb-2" style="font-size: 0.8rem;">Set up active tuition billing structure.</span>
                                        <a href="{{ route('imports.wizard', ['module' => 'fee-structures']) }}" class="btn btn-sm btn-link p-0 text-primary fw-bold text-decoration-none">Launch Import Wizard <i class="fas fa-chevron-right ms-1"></i></a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Step 5: Admissions -->
                        <div class="col-md-4">
                            <div class="p-3 bg-white shadow-sm rounded-3 h-100 d-flex align-items-start">
                                <div class="bg-secondary text-white p-2 rounded-3 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-file-signature"></i></div>
                                <div>
                                    <strong class="d-block text-dark">⑤ Begin Admissions</strong>
                                    <span class="text-muted d-block" style="font-size: 0.8rem;">Open registration & documents verification.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            <div class="row mb-4">
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-students'))
                <div class="col-md-2 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h5 class="card-title">Total Students</h5>
                            <h2 class="mb-0">{{ number_format($stats['total_students'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
                @endif
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-teachers'))
                <div class="col-md-2 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                            <h5 class="card-title">Total Teachers</h5>
                            <h2 class="mb-0">{{ number_format($stats['total_teachers'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
                @endif
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-attendance'))
                <div class="col-md-2 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <h5 class="card-title">Today's Attendance</h5>
                            <h2 class="mb-0">{{ number_format($stats['today_attendance'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
                @endif
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
                <div class="col-md-2 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                            <h5 class="card-title">Pending Fees</h5>
                            <h2 class="mb-0">₹{{ number_format($stats['pending_fees'] ?? 0, 2) }}</h2>
                        </div>
                    </div>
                </div>
                @endif
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-exams'))
                <div class="col-md-2 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                            <h5 class="card-title">Upcoming Exams</h5>
                            <h2 class="mb-0">{{ $stats['upcoming_exams'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
                @endif
                <div class="col-md-2 mb-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-2x mb-2"></i>
                            <h5 class="card-title">Notices</h5>
                            <h2 class="mb-0">{{ $stats['notices_count'] ?? 0 }}</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
            <!-- Statistics Cards Row 2 -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-light border-start border-primary border-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-day fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-muted mb-0">Today's Collection</p>
                                    <h4 class="mb-0">₹{{ number_format($stats['today_collection'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-light border-start border-success border-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-line fa-2x text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-muted mb-0">Monthly Revenue</p>
                                    <h4 class="mb-0">₹{{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-light border-start border-info border-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-user-clock fa-2x text-info"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-muted mb-0">Defaulter Count</p>
                                    <h4 class="mb-0">{{ \App\Models\Student::count() * 0.1 ?? 0 }}</h4> <!-- Placeholder -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Fee Collection Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="feeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-percentage"></i> Monthly Revenue</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Quick Access Links -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-rocket"></i> Quick Access</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-students'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-graduation-cap"></i><br>
                                        Students
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-teachers'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-success w-100">
                                        <i class="fas fa-chalkboard-teacher"></i><br>
                                        Teachers
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-success w-100" style="background-color: #28a745 !important; color: white !important; font-weight: bold;">
                                        <i class="fas fa-link"></i><br>
                                        Teacher Assign
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-attendance'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.attendance.index') }}" class="btn btn-outline-info w-100">
                                        <i class="fas fa-calendar-check"></i><br>
                                        Attendance
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-money-bill-wave"></i><br>
                                        Fees
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-exams'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-clipboard-list"></i><br>
                                        Exams
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-results'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.results.index') }}" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-award"></i><br>
                                        Results
                                    </a>
                                </div>
                                @endif
                            </div>
                            <div class="row mt-3">
                                @if(Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-classes'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-school"></i><br>
                                        Classes
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-sections'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.sections.index') }}" class="btn btn-outline-success w-100">
                                        <i class="fas fa-door-open"></i><br>
                                        Sections
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-subjects'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-info w-100">
                                        <i class="fas fa-book"></i><br>
                                        Subjects
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-academic-sessions'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.academic-sessions.index') }}" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-calendar-alt"></i><br>
                                        Sessions
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.fee-dashboard') }}" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-chart-line"></i><br>
                                        Fee Dash
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.fees.pending') }}" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-exclamation-triangle"></i><br>
                                        Pending
                                    </a>
                                </div>
                                @endif
                                @if(Auth::user()->hasRole('admin'))
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.accounts.index') }}" class="btn btn-outline-dark w-100">
                                        <i class="fas fa-user-shield"></i><br>
                                        Accounts
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🗄️ Universal Data Imports Dashboard Integration -->
    @if(Auth::user()->hasRole(['admin', 'super-admin']))
    <div class="row mt-4">
        <!-- Quick Import Wizard Actions -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-file-import text-primary me-2"></i> Quick Ingestion Actions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Launch multi-step CSV / Excel Guided Import Wizards directly to onboard school resources.</p>
                    <div class="d-grid gap-3">
                        <a href="{{ route('imports.wizard', ['module' => 'students']) }}" class="btn btn-primary text-start d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-graduation-cap me-2"></i> Import Students</span>
                            <i class="fas fa-chevron-right text-white-50"></i>
                        </a>
                        <a href="{{ route('imports.wizard', ['module' => 'teachers']) }}" class="btn btn-success text-start d-flex justify-content-between align-items-center disabled opacity-75">
                            <span><i class="fas fa-chalkboard-teacher me-2"></i> Import Teachers <small>(Soon)</small></span>
                            <i class="fas fa-lock text-white-50"></i>
                        </a>
                        <a href="{{ route('imports.wizard', ['module' => 'parents']) }}" class="btn btn-warning text-start d-flex justify-content-between align-items-center disabled opacity-75 text-dark">
                            <span><i class="fas fa-users-cog me-2"></i> Import Parents <small>(Soon)</small></span>
                            <i class="fas fa-lock text-dark-50"></i>
                        </a>
                        <a href="{{ route('imports.wizard', ['module' => 'fee-structures']) }}" class="btn btn-info text-start d-flex justify-content-between align-items-center disabled opacity-75 text-white">
                            <span><i class="fas fa-file-invoice-dollar me-2"></i> Import Fee Structures <small>(Soon)</small></span>
                            <i class="fas fa-lock text-white-50"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Imports Widget -->
        <div class="col-md-8 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clock text-primary me-2"></i> Recent Imports Tracking</h5>
                    <a href="{{ route('imports.dashboard') }}" class="btn btn-xs btn-outline-primary py-0">Imports Dashboard</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Module</th>
                                    <th>Imported By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Rows</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentImports as $import)
                                    <tr>
                                        <td><span class="badge bg-secondary text-capitalize">{{ $import->module }}</span></td>
                                        <td>{{ $import->user->name ?? 'Administrator' }}</td>
                                        <td>{{ $import->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            @if($import->status === 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($import->status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @elseif($import->status === 'rolled_back')
                                                <span class="badge bg-warning text-dark">Rolled Back</span>
                                            @else
                                                <span class="badge bg-info text-white">{{ ucfirst($import->status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $import->success_rows }} / {{ $import->total_rows }}</td>
                                        <td>
                                            @if($import->error_rows > 0)
                                                <span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i>{{ $import->error_rows }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No data ingestion logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Admission Enquiries Widget -->
    @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('super-admin'))
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0 py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-comments text-primary me-2"></i> Recent Admission Enquiries</h5>
                    <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-xs btn-outline-primary py-0">View All Enquiries</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Candidate Name</th>
                                    <th>Parent Contact</th>
                                    <th>Assigned Counsellor</th>
                                    <th>Status</th>
                                    <th>Follow-Up Date</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentEnquiries as $enquiry)
                                    <tr>
                                        <td class="fw-bold">{{ $enquiry->candidate_name }}</td>
                                        <td>{{ $enquiry->parent_name }} ({{ $enquiry->phone }})</td>
                                        <td>
                                            @if($enquiry->counsellor)
                                                <i class="fas fa-user-tie text-muted me-1"></i>{{ $enquiry->counsellor->name }}
                                            @else
                                                <span class="text-muted small">Not Assigned</span>
                                            @endif
                                        </td>
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
                                                {{ $enquiry->follow_up_date->format('Y-m-d') }}
                                            @else
                                                <span class="text-muted small">N/A</span>
                                            @endif
                                        </td>
                                        <td>{{ $enquiry->created_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No recent admission enquiries logged.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm border-info">
                <div class="card-header bg-info text-white py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-comments me-2"></i> My Assigned Admission Enquiries</h5>
                    <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-xs btn-outline-light py-0">View All My Enquiries</a>
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
                                @forelse($myEnquiries as $enquiry)
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
                                                {{ $enquiry->follow_up_date->format('Y-m-d') }}
                                            @else
                                                <span class="text-muted small">Not Scheduled</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ Str::limit($enquiry->remarks ?: ($enquiry->follow_up_notes ?: 'N/A'), 80) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No admission candidates currently assigned to you for counselling.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Fee Collection Chart
const feeCanvas = document.getElementById('feeChart');
if (feeCanvas) {
    const feeCtx = feeCanvas.getContext('2d');
    const feeChart = new Chart(feeCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Fee Collection (₹)',
                data: [45000, 52000, 48000, 61000, 55000, 68000],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Fee Collection Trend'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Revenue Chart
const revenueCanvas = document.getElementById('revenueChart');
if (revenueCanvas) {
    const revenueCtx = revenueCanvas.getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: ['Tuition', 'Exam', 'Library', 'Others'],
            datasets: [{
                label: 'Revenue Sources',
                data: [55, 20, 15, 10],
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Revenue Distribution'
                }
            }
        }
    });
}
</script>
@endsection