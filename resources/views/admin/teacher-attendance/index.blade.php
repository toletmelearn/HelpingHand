@extends('layouts.admin')

@section('title', 'Teacher Attendance Management')

@section('content')
<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2 text-gray-800">Teacher Attendance</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Teacher Attendance</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.teacher-attendance.reports') }}" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up"></i> Analytics
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Faculty
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people-fill fa-2x text-gray-300"></i>
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
                                Present Today
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['present'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle-fill fa-2x text-gray-300"></i>
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
                                Attendance Rate
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['attendance_rate'] }}%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-percent fa-2x text-gray-300"></i>
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
                                Current Date
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-{{ $isSunday ? 'warning' : 'success' }}">
                                    {{ $isSunday ? 'Weekend - No Classes' : 'Working Day' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-date fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Daily Attendance Records</h4>
                        <div class="d-flex gap-3 align-items-center">
                            @if(!$isSunday)
                            <form method="POST" action="{{ route('admin.teacher-attendance.mark-all-present') }}" 
                                  class="d-inline" id="markAllPresentForm">
                                @csrf
                                <input type="hidden" name="date" value="{{ $date }}">
                                <button type="button" 
                                        class="btn btn-success btn-lg shadow-sm border-0 px-4 py-3"
                                        onclick="confirmMarkAllPresent()" 
                                        {{ $allPresent ? 'disabled' : '' }}
                                        id="markAllPresentButton"
                                        style="font-weight: 600; letter-spacing: 0.5px; border-radius: 8px; transition: all 0.3s ease;">
                                    <i class="bi bi-check-all me-2 fs-5"></i>
                                    MARK ALL TEACHERS PRESENT
                                </button>
                            </form>
                            @endif
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#filterCollapse" aria-expanded="false">
                                <i class="bi bi-funnel me-1"></i>
                                Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="collapse" id="filterCollapse">
                    <div class="card-body border-bottom bg-light">
                        <form method="GET" action="{{ route('admin.teacher-attendance.index') }}" class="row g-3">
                            <div class="col-md-4">
                                <label for="dateFilter" class="form-label">Attendance Date</label>
                                <input type="date" class="form-control" id="dateFilter" 
                                       name="date" value="{{ $date }}">
                            </div>
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter" name="status">
                                    <option value="">All Status</option>
                                    <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                                    <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="departmentFilter" class="form-label">Department</label>
                                <select class="form-select" id="departmentFilter" name="department">
                                    <option value="">All Departments</option>
                                    @foreach($departments ?? [] as $dept)
                                    <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Apply Filters
                                </button>
                                <a href="{{ route('admin.teacher-attendance.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Teacher</th>
                                    <th>ID</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Check-in Time</th>
                                    <th>Remarks</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachers as $teacher)
                                @php
                                    $attendance = $teacher->teacherAttendances->first();
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="avatar-sm">
                                                    <div class="avatar-title bg-light text-primary rounded-circle">
                                                        {{ substr($teacher->name, 0, 1) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">{{ $teacher->name }}</h6>
                                                <small class="text-muted">{{ $teacher->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">#{{ $teacher->employee_id ?? $teacher->id }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            {{ $teacher->department ?? $teacher->wing ?? 'General' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($attendance)
                                            <span class="badge bg-{{ $attendance->getStatusBadge() }} rounded-pill">
                                                <i class="bi {{ $attendance->getStatusIcon() }} me-1"></i>
                                                {{ $attendance->getStatusText() }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary rounded-pill">
                                                <i class="bi bi-clock me-1"></i> Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($attendance && $attendance->status == 'present')
                                            <small class="text-muted">
                                                {{ $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('g:i A') : '-' }}
                                            </small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($attendance && $attendance->remarks)
                                            <span class="d-inline-block text-truncate" style="max-width: 150px;" 
                                                  data-bs-toggle="tooltip" title="{{ $attendance->remarks }}">
                                                {{ $attendance->remarks }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <!-- Quick Action Buttons -->
                                            <div class="btn-group btn-group-sm me-2" role="group">
                                                @if(!$attendance || $attendance->status !== 'present')
                                                    <button type="button" 
                                                            class="btn btn-outline-success btn-sm mark-present"
                                                            data-teacher-id="{{ $teacher->id }}"
                                                            data-date="{{ $date }}"
                                                            data-bs-toggle="tooltip" 
                                                            title="Mark Present">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                @endif
                                                
                                                @if(!$attendance || $attendance->status !== 'absent')
                                                    <button type="button" 
                                                            class="btn btn-outline-danger btn-sm mark-absent"
                                                            data-teacher-id="{{ $teacher->id }}"
                                                            data-date="{{ $date }}"
                                                            data-bs-toggle="tooltip" 
                                                            title="Mark Absent">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                @endif
                                            </div>
                                            
                                            <!-- Action Menu -->
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('admin.teacher-attendance.show', $teacher->id) }}" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-warning"
                                                        onclick="editAttendance({{ $teacher->id }})"
                                                        data-bs-toggle="tooltip" title="Edit Attendance">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="{{ route('admin.teachers.show', $teacher->id) }}" 
                                                   class="btn btn-outline-info"
                                                   data-bs-toggle="tooltip" title="View Profile">
                                                    <i class="bi bi-person"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="bi bi-people display-1 text-muted"></i>
                                            <h4 class="mt-3">No Attendance Records</h4>
                                            <p class="text-muted mb-4">
                                                @if($isSunday)
                                                    No attendance records for Sundays. Classes are not scheduled.
                                                @else
                                                    No teacher attendance records found for {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
                                                @endif
                                            </p>
                                            <a href="{{ route('admin.teacher-attendance.create') }}" class="btn btn-primary">
                                                <i class="bi bi-plus-circle me-1"></i>
                                                Create Attendance Record
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Table Footer -->
                <div class="card-footer bg-light border-top">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="text-muted small">
                            <i class="bi bi-people me-1"></i>
                            Showing {{ $teachers->firstItem() ?? 0 }} to {{ $teachers->lastItem() ?? 0 }} 
                            of {{ $teachers->total() }} entries
                        </div>
                        <div class="pagination-wrapper">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    @php
                                        // Get the paginator object
                                        $paginator = $teachers;
                                        $currentPage = $paginator->currentPage();
                                        $lastPage = $paginator->lastPage();
                                        $hasPages = $paginator->hasPages();
                                    @endphp
                                    
                                    @if ($hasPages)
                                        {{-- Previous Button --}}
                                        <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        {{-- First Page --}}
                                        @if ($currentPage > 2)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
                                            </li>
                                            @if ($currentPage > 3)
                                                <li class="page-item disabled"><span class="page-link">…</span></li>
                                            @endif
                                        @endif
                                        
                                        {{-- Pages Around Current --}}
                                        @for ($i = max(1, $currentPage - 1); $i <= min($lastPage, $currentPage + 1); $i++)
                                            @if($i != 1 && $i != $lastPage)  {{-- Skip first and last since handled separately --}}
                                                <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                                    <a class="page-link" href="{{ $paginator->url($i) }}">{{ $i }}</a>
                                                </li>
                                            @endif
                                        @endfor
                                        
                                        {{-- Last Page --}}
                                        @if ($currentPage < $lastPage - 1)
                                            @if ($currentPage < $lastPage - 2)
                                                <li class="page-item disabled"><span class="page-link">…</span></li>
                                            @endif
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a>
                                            </li>
                                        @endif
                                        
                                        {{-- Next Button --}}
                                        <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Attendance Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="{{ route('admin.teacher-attendance.export') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Export Format</label>
                        <select class="form-select" id="exportFormat" name="format" required>
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel Spreadsheet</option>
                            <option value="csv">CSV File</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" 
                                   value="{{ $date }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" 
                                   value="{{ $date }}" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeDetails" name="include_details">
                            <label class="form-check-label" for="includeDetails">
                                Include detailed remarks
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="groupByDept" name="group_by_department">
                            <label class="form-check-label" for="groupByDept">
                                Group by department
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i> Export Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal (Dynamic) -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAttendanceForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="attendanceStatus" class="form-label">Status</label>
                        <select class="form-select" id="attendanceStatus" name="status" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late Arrival</option>
                            <option value="leave">On Leave</option>
                            <option value="half_day">Half Day</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="checkInTime" class="form-label">Check-in Time</label>
                        <input type="time" class="form-control" id="checkInTime" name="check_in">
                    </div>
                    <div class="mb-3">
                        <label for="attendanceRemarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="attendanceRemarks" name="remarks" 
                                  rows="3" placeholder="Add any remarks or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
/* Professional styling */
.avatar-sm {
    width: 40px;
    height: 40px;
}

.avatar-title {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-weight: 600;
    font-size: 1rem;
}

/* Table styling */
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
}

.table > :not(:first-child) {
    border-top: 1px solid #dee2e6;
}

/* Card styling */
.card {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    border: 1px solid #e3e6f0;
}

.card-header {
    border-bottom: 1px solid #e3e6f0;
    background-color: #f8f9fc;
}

.card-header h4 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0;
}

/* Border utilities */
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

/* Status badges */
.badge.bg-present {
    background-color: #d1e7dd;
    color: #0f5132;
}

.badge.bg-absent {
    background-color: #f8d7da;
    color: #842029;
}

.badge.bg-late {
    background-color: #fff3cd;
    color: #664d03;
}

.badge.bg-leave {
    background-color: #cfe2ff;
    color: #084298;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
    
    .avatar-sm {
        width: 32px;
        height: 32px;
    }
    
    .avatar-title {
        font-size: 0.875rem;
    }
}

/* Animation for filter collapse */
.collapsing {
    transition: height 0.35s ease;
}

/* Tooltip customization */
.tooltip {
    font-size: 0.875rem;
}

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Professional ERP Mark All Present Button */
.btn-success.btn-lg {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    font-weight: 600;
}

.btn-success.btn-lg:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    background: linear-gradient(135deg, #218838 0%, #1baa80 100%);
}

.btn-success.btn-lg:disabled {
    background: #6c757d;
    box-shadow: none;
    transform: none;
    cursor: not-allowed;
}

/* Professional pagination styling */
.pagination {
    margin-bottom: 0;
}

.page-item {
    border-radius: 4px;
    margin: 0 2px;
}

.page-link {
    border-radius: 4px;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e0e0e0;
    color: #495057;
    transition: all 0.2s;
}

.page-link:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
    color: #0d6efd;
}

.page-item.active .page-link {
    background-color: #4e73df;
    border-color: #4e73df;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.page-item.disabled .page-link {
    color: #6c757d;
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.page-link:focus {
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

/* Pagination wrapper styling */
.pagination-wrapper {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* Responsive pagination */
@media (max-width: 768px) {
    .pagination-wrapper {
        overflow-x: auto;
        white-space: nowrap;
        padding: 0.25rem 0.5rem;
    }
    
    .page-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection

@section('scripts')
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Professional ERP Mark All Present Function
function confirmMarkAllPresent() {
    const form = document.getElementById('markAllPresentForm');
    if (!form) {
        alert('System error: Form not found. Please refresh the page.');
        return;
    }
    
    const date = new Date('{{ $date }}');
    const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Professional confirmation dialog
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Confirm Bulk Attendance Update',
            html: `Mark <strong>all teachers</strong> as present for <strong>${formattedDate}</strong>?<br><br>
                  <small class="text-muted">This will update attendance records for all faculty members.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirm Attendance',
            cancelButtonText: 'Cancel',
            width: '500px'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    } else {
        // Fallback for environments without SweetAlert
        if (confirm(`Mark all teachers as present for ${formattedDate}?`)) {
            form.submit();
        }
    }
}

// Edit attendance function
function editAttendance(teacherId) {
    // Fetch current attendance data
    fetch(`/admin/teacher-attendance/${teacherId}/edit?date={{ $date }}`)
        .then(response => response.json())
        .then(data => {
            // Set form action
            const form = document.getElementById('editAttendanceForm');
            form.action = `/admin/teacher-attendance/${teacherId}`;
            
            // Set current values
            if (data.attendance) {
                document.getElementById('attendanceStatus').value = data.attendance.status;
                document.getElementById('checkInTime').value = data.attendance.check_in || '';
                document.getElementById('attendanceRemarks').value = data.attendance.remarks || '';
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load attendance data'
            });
        });
}

// Date range validation for export
document.getElementById('exportModal').addEventListener('show.bs.modal', function () {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    // Set max date to today
    const today = new Date().toISOString().split('T')[0];
    startDate.max = today;
    endDate.max = today;
    
    // Validate end date is after start date
    startDate.addEventListener('change', function() {
        endDate.min = this.value;
    });
});

// Real-time attendance updates (WebSocket simulation)
function updateAttendanceStatus(teacherId, status) {
    const badge = document.querySelector(`tr[data-teacher-id="${teacherId}"] .status-badge`);
    if (badge) {
        badge.className = `badge bg-${status} rounded-pill`;
        badge.innerHTML = `<i class="bi bi-check-circle me-1"></i> ${status.charAt(0).toUpperCase() + status.slice(1)}`;
    }
}

// Auto-refresh page every 5 minutes if on today's date
if (new Date('{{ $date }}').toDateString() === new Date().toDateString()) {
    setTimeout(() => {
        window.location.reload();
    }, 300000); // 5 minutes
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F for filter
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const filterCollapse = document.getElementById('filterCollapse');
        const bsCollapse = new bootstrap.Collapse(filterCollapse, {
            toggle: true
        });
    }
    
    // Ctrl + P for print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});

// Quick Action Buttons for Individual Teacher Attendance
function initQuickActionButtons() {
    // Handle Mark Present button clicks
    document.querySelectorAll('.mark-present').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const teacherId = this.getAttribute('data-teacher-id');
            const date = this.getAttribute('data-date');
            
            // Show confirmation
            Swal.fire({
                title: 'Mark Present?',
                text: 'Are you sure you want to mark this teacher as present?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Mark Present',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAttendanceStatus(teacherId, date, 'present');
                }
            });
        });
    });
    
    // Handle Mark Absent button clicks
    document.querySelectorAll('.mark-absent').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const teacherId = this.getAttribute('data-teacher-id');
            const date = this.getAttribute('data-date');
            
            // Show confirmation
            Swal.fire({
                title: 'Mark Absent?',
                text: 'Are you sure you want to mark this teacher as absent?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Mark Absent',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAttendanceStatus(teacherId, date, 'absent');
                }
            });
        });
    });
}

// Update individual teacher attendance
function updateAttendanceStatus(teacherId, date, status) {
    // Show loading
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait while we update the attendance status',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`/admin/teacher-attendance/update-attendance/${teacherId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            date: date,
            status: status,
            remarks: `Manually marked ${status} by admin`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: data.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                // Reload the page to reflect changes
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to update attendance. Please try again.'
        });
        console.error('Error:', error);
    });
}

// Initialize quick action buttons when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initQuickActionButtons();
});

// Print optimized view
function printAttendance() {
    const originalContent = document.body.innerHTML;
    const printContent = document.querySelector('.card').innerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Teacher Attendance - {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</title>
            <style>
                @media print {
                    body { margin: 0; padding: 20px; }
                    .no-print { display: none !important; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; }
                    th { background-color: #f5f5f5; }
                    .badge { border: 1px solid #ccc; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2>Teacher Attendance Report</h2>
                <p>Date: {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</p>
                <p>Generated: {{ now()->format('F j, Y g:i A') }}</p>
            </div>
            ${printContent}
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>
@endsection

@push('scripts')
<!-- Include SweetAlert2 for better alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Show success message if exists
@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '{{ session("success") }}',
    timer: 3000,
    showConfirmButton: false
});
@endif

// Show error message if exists
@if(session('error'))
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '{{ session("error") }}',
    timer: 4000,
    showConfirmButton: false
});
@endif
</script>
@endpush