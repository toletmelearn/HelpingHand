@extends('layouts.admin')

@section('title', 'Student Attendance Management')

@section('content')
<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2 text-gray-800">Student Attendance</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Student Attendance</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('attendance.reports') }}" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up"></i> Analytics
                    </a>
                    @php
                        $exportParams = ['format' => 'csv'];
                        if (request()->filled('date')) {
                            $exportParams['from_date'] = request('date');
                            $exportParams['to_date'] = request('date');
                        }
                        if (request()->filled('class')) {
                            $exportParams['class'] = request('class');
                        }
                        $exportableStatuses = ['present', 'absent', 'late', 'half_day'];
                        if (request()->filled('status') && in_array(request('status'), $exportableStatuses, true)) {
                            $exportParams['status'] = request('status');
                        }
                    @endphp
                    <a href="{{ route('attendance.export', $exportParams) }}" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
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
                                Total Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_students'] ?? 0 }}</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['present_today'] ?? 0 }}</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['attendance_rate'] ?? 0 }}%</div>
                            <div class="small text-muted mt-1">Attendance credit policy: Present = 1, Late = 1, Half Day = 0.5, Absent = 0. Leave is legacy and gives 0 credit.</div>
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
                                {{ \Carbon\Carbon::parse(request('date', now()))->format('F j, Y') }}
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
                        <div class="d-flex gap-2">
                            <a href="{{ route('attendance.create') }}" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Mark Attendance
                            </a>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#filterCollapse" aria-expanded="false">
                                <i class="bi bi-funnel me-1"></i>
                                Filter
                            </button>
                        </div>
                    </div>
                </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

                <!-- Filter Section -->
                <div class="collapse" id="filterCollapse">
                    <div class="card-body border-bottom bg-light">
                        <form method="GET" action="{{ route('attendance.index') }}" class="row g-3">
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ request('date') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="class" class="form-label">Class</label>
                                <select class="form-select" id="class" name="class">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $cls)
                                        <option value="{{ $cls }}" {{ request('class') == $cls ? 'selected' : '' }}>
                                            {{ $cls }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                                    <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                                    <option value="half_day" {{ request('status') == 'half_day' ? 'selected' : '' }}>Half Day</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                    <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="card-body p-0">
                    @if($attendances->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Class</th>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Subject</th>
                                        <th>Period</th>
                                        <th>Marks By</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendances as $attendance)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="bi bi-calendar-event text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    {{ $attendance->date->format('d/m/Y') }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">
                                                <i class="bi bi-mortarboard me-1"></i>
                                                {{ $attendance->class }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($attendance->student)
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="avatar-sm">
                                                            <div class="avatar-title bg-light text-primary rounded-circle">
                                                                {{ substr($attendance->student->name, 0, 1) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <strong>{{ $attendance->student->name }}</strong>
                                                        <br><small class="text-muted">Roll: {{ $attendance->student->roll_number }}</small>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted">Student N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($attendance->status == 'present')
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Present
                                                </span>
                                            @elseif($attendance->status == 'absent')
                                                <span class="badge bg-danger-subtle text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    Absent
                                                </span>
                                            @elseif($attendance->status == 'late')
                                                <span class="badge bg-warning-subtle text-warning">
                                                    <i class="bi bi-clock me-1"></i>
                                                    Late
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">
                                                    <i class="bi bi-hourglass-split me-1"></i>
                                                    Half Day
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($attendance->subject)
                                                <span class="badge bg-primary-subtle text-primary">
                                                    {{ $attendance->subject }}
                                                </span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                {{ \App\Support\Attendance\AttendancePeriodPresenter::display($attendance->period) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="bi bi-person-badge text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    @if($attendance->markedBy)
                                                        {{ $attendance->markedBy->name }}
                                                    @else
                                                        System
                                                    @endif
                                                    <br><small class="text-muted">{{ $attendance->created_at->format('H:i') }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('attendance.show', $attendance) }}" 
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('attendance.edit', $attendance) }}" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-outline-secondary disabled"
                                                        disabled
                                                        title="Deletion is disabled until an audit-preserving correction workflow is enabled.">
                                                    <i class="bi bi-trash"></i>
                                                    <span class="visually-hidden">Delete disabled</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted">Showing {{ $attendances->firstItem() }} to {{ $attendances->lastItem() }} of {{ $attendances->total() }} records</span>
                                </div>
                                <div>
                                    {{ $attendances->links() }}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                            </div>
                            <h5 class="text-muted">No Attendance Records Found</h5>
                            <p class="text-muted mb-4">No attendance records match your current filters.</p>
                            <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Mark Attendance Now
                            </a>
                            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
