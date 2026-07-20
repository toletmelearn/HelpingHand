@extends('layouts.admin')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <a href="{{ route('admin.leaves.index') }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Leave Requests
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 55px; height: 55px; font-weight: 600; font-size: 1.2rem;">
                            {{ strtoupper(substr($leave->teacher->name, 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="mb-0 text-dark font-weight-bold">{{ $leave->teacher->name }}</h3>
                            <p class="text-secondary mb-0">Teacher Profile | {{ $leave->teacher->email ?? $leave->teacher->phone }}</p>
                        </div>
                    </div>

                    <hr class="my-4 text-light">

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <span class="text-secondary d-block mb-1">Leave Type</span>
                            <span class="badge bg-secondary text-capitalize px-3 py-2" style="font-size: 0.9rem;">
                                {{ str_replace('_', ' ', $leave->leave_type) }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary d-block mb-1">Requested Duration</span>
                            <span class="text-dark font-weight-bold" style="font-size: 1rem;">
                                {{ $leave->start_date->format('M d, Y') }} - {{ $leave->end_date->format('M d, Y') }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary d-block mb-1">Total Leave Days</span>
                            <span class="text-dark font-weight-bold" style="font-size: 1rem;">{{ $leave->days }} Days</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="text-secondary d-block mb-1">Reason for Leave</span>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap; font-size: 0.95rem;">{{ $leave->reason }}</div>
                    </div>

                    @if($leave->status != 'pending')
                        <div class="p-3 rounded mb-4 {{ $leave->status == 'approved' ? 'bg-success-light text-success' : 'bg-danger-light text-danger' }}" style="background-color: {{ $leave->status == 'approved' ? '#e8f5e9' : '#ffebee' }};">
                            <h6 class="font-weight-bold mb-1">Leave Request {{ ucfirst($leave->status) }}</h6>
                            <p class="mb-1 text-dark">Processed by <strong>{{ $leave->approver->name ?? 'System' }}</strong> on {{ $leave->approved_at ? $leave->approved_at->format('M d, Y') : $leave->updated_at->format('M d, Y') }}</p>
                            @if($leave->approval_notes)
                                <div class="mt-2 p-2 bg-white rounded text-dark border border-light" style="font-size: 0.9rem;">
                                    <strong>Notes:</strong> {{ $leave->approval_notes }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($leave->status == 'pending')
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="card-title text-dark font-weight-bold mb-0">Take Action</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <form action="{{ route('admin.leaves.update', $leave->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="status" class="form-label text-secondary">Decision</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="" disabled selected>Select an option</option>
                                    <option value="approved">Approve Application</option>
                                    <option value="rejected">Reject Application</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="approval_notes" class="form-label text-secondary">Decision Notes / Remarks</label>
                                <textarea class="form-select text-dark p-2" id="approval_notes" name="approval_notes" rows="4" placeholder="Write feedback or reason for rejection..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill font-weight-bold">Submit Decision</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
