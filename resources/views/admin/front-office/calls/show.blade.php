@extends('layouts.admin')

@section('title', 'Call Register Details')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.calls.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Register
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Phone Call Log Details</h1>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Call Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-bordered align-middle">
                        <tbody>
                            <tr>
                                <th class="w-40 text-muted small text-uppercase">Caller Name</th>
                                <td><strong>{{ $call->caller_name }}</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Phone / Contact</th>
                                <td><span class="font-monospace fw-semibold">{{ $call->phone }}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Call Direction</th>
                                <td>
                                    <span class="badge bg-{{ $call->call_type === 'incoming' ? 'info' : 'primary' }} text-capitalize">
                                        {{ $call->call_type }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Call Purpose</th>
                                <td><span class="text-capitalize">{{ str_replace('_', ' ', $call->purpose) }}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Duration</th>
                                <td>{{ floor($call->duration / 60) }} mins {{ $call->duration % 60 }} secs</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Assigned Employee</th>
                                <td>{{ $call->assignedUser ? $call->assignedUser->name : 'Unassigned' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Logged At</th>
                                <td>{{ $call->created_at->format('M d, Y h:i A') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Call Outcome & Resolution</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="d-block text-uppercase small text-muted mb-1">Status</label>
                        <span class="badge bg-{{ $call->status === 'completed' ? 'success' : ($call->status === 'missed' ? 'danger' : 'warning') }} rounded-pill text-capitalize fs-6 px-3">
                            {{ str_replace('_', ' ', $call->status) }}
                        </span>
                    </div>

                    @if($call->follow_up_date)
                        <div class="mb-4 bg-warning bg-opacity-10 border border-warning p-3 rounded">
                            <label class="d-block text-uppercase small text-warning-emphasis mb-1 fw-bold"><i class="bi bi-bell-fill me-1"></i>Scheduled Follow-up Date</label>
                            <h5 class="mb-0 text-warning-emphasis fw-bold">{{ $call->follow_up_date->format('F d, Y') }}</h5>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="d-block text-uppercase small text-muted mb-1">Discussion & Outcome Details</label>
                        <div class="p-3 bg-light rounded border border-light font-monospace small" style="white-space: pre-wrap;">{{ $call->outcome ?: 'No outcome discussion logged.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
