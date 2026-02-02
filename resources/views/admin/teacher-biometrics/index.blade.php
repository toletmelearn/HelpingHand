@extends('layouts.admin')

@section('title', 'Teacher Biometric & Working Hours Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-fingerprint"></i> Teacher Biometric & Working Hours
            </h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_teachers'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Present Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['present_today'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Late Arrivals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['late_arrivals'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Early Departures</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['early_departures'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-box-arrow-left fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Half Days</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['half_days'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-x fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Avg Hours</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['avg_working_hours'], 1) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-stopwatch fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('admin.teacher-biometrics.create') }}" class="btn btn-primary btn-block">
                                <i class="bi bi-plus-circle"></i> Add Manual Record
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('admin.teacher-biometrics.settings') }}" class="btn btn-info btn-block">
                                <i class="bi bi-gear"></i> Configure Settings
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('admin.teacher-biometrics.reports') }}" class="btn btn-success btn-block">
                                <i class="bi bi-bar-chart"></i> View Reports
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-warning btn-block" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="bi bi-upload"></i> Upload CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filter Records</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.teacher-biometrics.index') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ $date }}">
                            </div>
                            <div class="col-md-4">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select class="form-select" id="teacher_id" name="teacher_id">
                                    <option value="">All Teachers</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }} ({{ $teacher->employee_id }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                                <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Biometric Records</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Teacher</th>
                                    <th>Employee ID</th>
                                    <th>First In</th>
                                    <th>Last Out</th>
                                    <th>Working Hours</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $record)
                                    <tr>
                                        <td>{{ $record->date->format('Y-m-d') }}</td>
                                        <td>{{ $record->teacher->name }}</td>
                                        <td>{{ $record->teacher->employee_id ?? 'N/A' }}</td>
                                        <td>{{ $record->first_in_time ? \Carbon\Carbon::createFromTimeString($record->first_in_time)->format('H:i') : 'N/A' }}</td>
                                        <td>{{ $record->last_out_time ? \Carbon\Carbon::createFromTimeString($record->last_out_time)->format('H:i') : 'N/A' }}</td>
                                        <td>{{ $record->working_duration_formatted }}</td>
                                        <td>
                                            <span class="badge {{ $record->status_badge['class'] }}">
                                                {{ $record->status_badge['text'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.teacher-biometrics.show', $record) }}" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.teacher-biometrics.edit', $record) }}" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.teacher-biometrics.destroy', $record) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        {{ $records->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Biometric Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.teacher-biometrics.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file" class="form-label">Select CSV/XLSX File</label>
                        <input type="file" class="form-control" id="file" name="file" accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">File should contain columns: Teacher ID, Date, First In Time, Last Out Time, Remarks</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="overwrite" name="overwrite" value="1">
                        <label class="form-check-label" for="overwrite">Overwrite existing records</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
