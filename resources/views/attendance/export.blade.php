@extends('layouts.admin')

@section('title', 'Export Attendance Data')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-download"></i> Export Attendance Data</h2>
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Attendance
        </a>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-down"></i> Export Options</h5>
                </div>
                <div class="card-body">
                    <p class="lead">Choose a date range, optional class, and optional status filter for attendance CSV export.</p>
                    
                    <form method="GET" action="{{ route('attendance.export') }}">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="from_date" class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="from_date" name="from_date" 
                                           value="{{ request('from_date', now()->subDays(30)->toDateString()) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="to_date" class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="to_date" name="to_date" 
                                           value="{{ request('to_date', now()->toDateString()) }}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="class" class="form-label">Filter by Class (Optional)</label>
                            <select class="form-select" id="class" name="class">
                                <option value="">All Classes</option>
                                @foreach($classes as $className)
                                    <option value="{{ $className }}" {{ request('class') == $className ? 'selected' : '' }}>
                                        {{ $className }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Filter by Status (Optional)</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                                <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                                <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                                <option value="half_day" {{ request('status') == 'half_day' ? 'selected' : '' }}>Half Day</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <button type="submit" class="btn btn-success flex-fill" name="format" value="csv">
                                <i class="bi bi-filetype-csv"></i> Export CSV
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-fill" disabled>
                                <i class="bi bi-filetype-xlsx"></i> Excel not enabled
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-fill" disabled>
                                <i class="bi bi-file-pdf"></i> PDF not enabled
                            </button>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Excel and PDF export are not enabled yet.</p>
                    </form>
                </div>
            </div>
            
            <!-- Quick Export Options -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-speedometer2"></i> Quick Export Options</h5>
                </div>
                <div class="card-body">
                    @php
                        $quickExportFilters = [];
                        if (request()->filled('class')) {
                            $quickExportFilters['class'] = request('class');
                        }
                        $quickExportStatuses = ['present', 'absent', 'late', 'half_day'];
                        if (in_array(request('status'), $quickExportStatuses, true)) {
                            $quickExportFilters['status'] = request('status');
                        }
                    @endphp
                    <p class="text-muted small mb-3">Quick exports use the selected class/status filters when available.</p>
                    <div class="row">
                        <div class="col-md-4">
                            <a href="{{ route('attendance.export', array_merge($quickExportFilters, ['format' => 'csv', 'from_date' => now()->subDays(7)->toDateString(), 'to_date' => now()->toDateString()])) }}" 
                               class="btn btn-outline-success w-100">
                                <i class="bi bi-calendar-week"></i> Last 7 Days (CSV)
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('attendance.export', array_merge($quickExportFilters, ['format' => 'csv', 'from_date' => now()->subDays(30)->toDateString(), 'to_date' => now()->toDateString()])) }}" 
                               class="btn btn-outline-primary w-100">
                                <i class="bi bi-calendar-month"></i> Last 30 Days (CSV)
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('attendance.export', array_merge($quickExportFilters, ['format' => 'csv', 'from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->endOfMonth()->toDateString()])) }}" 
                               class="btn btn-outline-info w-100">
                                <i class="bi bi-calendar-range"></i> This Month (CSV)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Export</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Exported data includes student information, attendance status, dates, and remarks</li>
                        <li>CSV exports are compatible with Excel and Google Sheets</li>
                        <li>Excel and PDF export are not enabled yet</li>
                        <li>Large datasets may take a moment to generate</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
