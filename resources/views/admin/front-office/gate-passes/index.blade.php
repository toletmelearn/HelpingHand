@extends('layouts.admin')

@section('title', 'Gate Pass Registry')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Gate Pass Manager</h1>
            <p class="text-muted mb-0">Issue, track, and verify student, staff, visitor, and vehicle exit gate passes.</p>
        </div>
        <a href="{{ route('admin.front-office.gate-passes.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Issue Gate Pass
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.front-office.gate-passes.index') }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="pass_type" class="form-select">
                        <option value="">Filter Type</option>
                        <option value="student" {{ request('pass_type') === 'student' ? 'selected' : '' }}>Student Pass</option>
                        <option value="staff" {{ request('pass_type') === 'staff' ? 'selected' : '' }}>Staff Pass</option>
                        <option value="visitor" {{ request('pass_type') === 'visitor' ? 'selected' : '' }}>Visitor Pass</option>
                        <option value="vehicle" {{ request('pass_type') === 'vehicle' ? 'selected' : '' }}>Vehicle Pass</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active / Out</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed / Returned</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th>Pass Holder</th>
                            <th>Pass Type</th>
                            <th>Purpose & Details</th>
                            <th>Departure</th>
                            <th>Arrival Time</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($passes as $pass)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $pass->holder_name }}</div>
                                    @if($pass->pass_type === 'student' && $pass->student)
                                        <small class="text-muted">Student Class: {{ $pass->student->schoolClass ? $pass->student->schoolClass->name : 'N/A' }}</small>
                                    @elseif($pass->pass_type === 'staff' && $pass->user)
                                        <small class="text-muted">Staff Member</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border text-capitalize">
                                        {{ $pass->pass_type }} Pass
                                    </span>
                                </td>
                                <td>
                                    <div>{{ $pass->purpose }}</div>
                                    @if($pass->vehicle_no)
                                        <small class="text-muted"><i class="bi bi-car-front-fill me-1"></i>Vehicle: {{ $pass->vehicle_no }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $pass->request_date->format('M d, Y') }}</div>
                                    <small class="text-muted font-monospace">{{ \Carbon\Carbon::parse($pass->departure_time)->format('h:i A') }}</small>
                                </td>
                                <td>
                                    @if($pass->arrival_time)
                                        <span class="font-monospace small">{{ \Carbon\Carbon::parse($pass->arrival_time)->format('h:i A') }}</span>
                                    @else
                                        <span class="text-muted small">Not Returned</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $pass->status === 'completed' ? 'success' : ($pass->status === 'active' ? 'primary' : ($pass->status === 'pending' ? 'warning' : 'danger')) }} rounded-pill text-capitalize">
                                        {{ $pass->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        @if($pass->status === 'active' || $pass->status === 'approved')
                                            <form action="{{ route('admin.front-office.gate-passes.verify', $pass->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success px-3" title="Verify Return & Complete Pass">
                                                    Verify Return
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.front-office.gate-passes.show', $pass->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" title="Print Gate Pass">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <a href="{{ route('admin.front-office.gate-passes.edit', $pass->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.front-office.gate-passes.destroy', $pass->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this gate pass?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No gate passes recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $passes->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
