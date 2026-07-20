@extends('layouts.admin')

@section('title', 'Call Register')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Phone Call Register</h1>
            <p class="text-muted mb-0">Log and trace incoming and outgoing telephone calls and follow-ups.</p>
        </div>
        <a href="{{ route('admin.front-office.calls.create') }}" class="btn btn-primary">
            <i class="bi bi-telephone-plus me-1"></i> Log Call
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
            <form action="{{ route('admin.front-office.calls.index') }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or number..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="call_type" class="form-select">
                        <option value="">Filter Type</option>
                        <option value="incoming" {{ request('call_type') === 'incoming' ? 'selected' : '' }}>Incoming</option>
                        <option value="outgoing" {{ request('call_type') === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="purpose" class="form-select">
                        <option value="">Filter Purpose</option>
                        <option value="admission" {{ request('purpose') === 'admission' ? 'selected' : '' }}>Admission Inquiry</option>
                        <option value="fee_reminder" {{ request('purpose') === 'fee_reminder' ? 'selected' : '' }}>Fee Reminder</option>
                        <option value="emergency" {{ request('purpose') === 'emergency' ? 'selected' : '' }}>Emergency</option>
                        <option value="general" {{ request('purpose') === 'general' ? 'selected' : '' }}>General Info</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Filter Status</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="missed" {{ request('status') === 'missed' ? 'selected' : '' }}>Missed Call</option>
                        <option value="follow_up_required" {{ request('status') === 'follow_up_required' ? 'selected' : '' }}>Follow-up Req</option>
                    </select>
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
                            <th>Caller Name & Phone</th>
                            <th>Call Type</th>
                            <th>Purpose</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Follow-up</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calls as $call)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $call->caller_name }}</div>
                                    <small class="text-muted font-monospace">{{ $call->phone }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $call->call_type === 'incoming' ? 'info' : 'primary' }} text-capitalize">
                                        {{ $call->call_type }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-capitalize small fw-semibold">{{ str_replace('_', ' ', $call->purpose) }}</div>
                                </td>
                                <td>
                                    {{ floor($call->duration / 60) }}m {{ $call->duration % 60 }}s
                                </td>
                                <td>
                                    <span class="badge bg-{{ $call->status === 'completed' ? 'success' : ($call->status === 'missed' ? 'danger' : 'warning') }} rounded-pill text-capitalize text-wrap">
                                        {{ str_replace('_', ' ', $call->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($call->follow_up_date)
                                        <div class="small fw-semibold text-danger">{{ $call->follow_up_date->format('M d, Y') }}</div>
                                    @else
                                        <span class="text-muted small">N/A</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('admin.front-office.calls.show', $call->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.front-office.calls.edit', $call->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.front-office.calls.destroy', $call->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this log?')">
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
                                <td colspan="7" class="text-center py-4 text-muted">No phone calls registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $calls->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
