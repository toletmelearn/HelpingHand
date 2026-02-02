@extends('layouts.admin')

@section('title', 'Advanced Reports Management')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-file-earmark-bar-graph"></i> Advanced Reports
        </h1>
        <a href="{{ route('admin.advanced-reports.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Report
        </a>
    </div>

    <!-- Dashboard Link -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        <a href="{{ route('admin.advanced-reports.dashboard') }}" class="alert-link">
            View Advanced Reporting Dashboard
        </a> for real-time analytics and KPIs.
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.advanced-reports.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="module" class="form-label">Module</label>
                    <select name="module" id="module" class="form-select">
                        <option value="">All Modules</option>
                        @foreach(['students' => 'Students', 'fees' => 'Fees', 'attendance' => 'Attendance', 'exams' => 'Exams', 'library' => 'Library', 'biometric' => 'Biometric'] as $key => $label)
                            <option value="{{ $key }}" {{ request('module') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="type" class="form-label">Report Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="">All Types</option>
                        @foreach(['kpi' => 'KPI', 'chart' => 'Chart', 'table' => 'Table', 'summary' => 'Summary'] as $key => $label)
                            <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="{{ route('admin.advanced-reports.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list"></i> Reports List</h5>
        </div>
        <div class="card-body">
            @if($reports->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Module</th>
                                <th>Type</th>
                                <th>Chart Type</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $report)
                                <tr>
                                    <td>
                                        <strong>{{ $report->name }}</strong>
                                        @if($report->description)
                                            <div class="small text-muted">{{ Str::limit($report->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ ucfirst($report->module) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ ucfirst($report->type) }}</span>
                                    </td>
                                    <td>
                                        @if($report->chart_type)
                                            <span class="badge bg-info">{{ ucfirst($report->chart_type) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($report->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $report->creator->name ?? 'System' }}
                                    </td>
                                    <td>
                                        {{ $report->created_at->format('M d, Y') }}
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('admin.advanced-reports.show', $report) }}" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.advanced-reports.edit', $report) }}" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.advanced-reports.destroy', $report) }}" 
                                                  method="POST" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this report?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    {{ $reports->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-x fs-1 text-muted"></i>
                    <h4 class="mt-3">No Reports Found</h4>
                    <p class="text-muted">Create your first advanced report to get started.</p>
                    <a href="{{ route('admin.advanced-reports.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Report
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
