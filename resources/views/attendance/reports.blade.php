@extends('layouts.admin')

@section('title', 'Student Attendance Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2 text-gray-800">Attendance Analytics</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Attendance</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Analytics</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    @php
                        $exportParams = ['format' => 'csv'];
                        if (request()->filled('date')) {
                            $exportParams['from_date'] = request('date');
                            $exportParams['to_date'] = request('date');
                        }
                        if (request()->filled('class')) {
                            $exportParams['class'] = request('class');
                        }
                    @endphp
                    <a href="{{ route('attendance.export', $exportParams) }}" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Reports</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.reports') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="{{ request('date', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="class" class="form-label">Class</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">All Classes</option>
                            @foreach($classes as $className)
                                <option value="{{ $className }}" {{ request('class') == $className ? 'selected' : '' }}>
                                    {{ $className }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    @if(isset($stats))
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students
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
                                Present
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
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Absent
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['absent'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-x-circle-fill fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['percentage'] }}%</div>
                            <div class="small text-muted mt-1">Attendance credit policy: Present = 1, Late = 1, Half Day = 0.5, Absent = 0. Leave is legacy and gives 0 credit.</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-percent fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Detailed Attendance Records -->
    @if(isset($attendances) && $attendances->count() > 0)
    <div class="card shadow border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Detailed Attendance Records</h5>
                <span class="badge bg-primary">Total: {{ $attendances->total() }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Subject</th>
                            <th>Period</th>
                            <th>Marks By</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendances as $attendance)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-light text-primary rounded-circle">
                                                {{ substr($attendance->student->name ?? 'N/A', 0, 1) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="font-weight-bold">
                                            {{ $attendance->student->name ?? 'Unknown Student' }}
                                        </div>
                                        <small class="text-muted">Roll: {{ $attendance->student->roll_number ?? 'N/A' }}</small>
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
                                        {{ ucfirst($attendance->status) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($attendance->subject)
                                    <span class="badge bg-primary-subtle text-primary">
                                        {{ $attendance->subject }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    {{ \App\Support\Attendance\AttendancePeriodPresenter::display($attendance->period) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-badge text-muted me-2"></i>
                                    <div>
                                        {{ $attendance->markedBy->name ?? 'System' }}
                                        <br><small class="text-muted">{{ $attendance->created_at->format('H:i') }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-event text-muted me-2"></i>
                                    {{ $attendance->date->format('M j, Y') }}
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('attendance.show', $attendance) }}" 
                                       class="btn btn-outline-info" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('attendance.edit', $attendance) }}" 
                                       class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
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
        </div>
    </div>
    @elseif(request()->hasAny(['date', 'class']))
    <div class="card shadow border-0">
        <div class="card-body text-center py-5">
            <div class="mb-3">
                <i class="bi bi-clipboard-data display-4 text-muted"></i>
            </div>
            <h5 class="text-muted">No Attendance Records Found</h5>
            <p class="text-muted mb-4">No attendance records match your current filters.</p>
            <a href="{{ route('attendance.reports') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
