@extends('layouts.admin')

@section('title', 'Discount Applications')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Discount Applications</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Discount Applications</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Discount Form (Admin and Accountant only) -->
    @if(auth()->user() && (auth()->user()->hasRole('admin') || auth()->user()->role === 'admin' || auth()->user()->hasRole('accountant') || auth()->user()->role === 'accountant'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0"><i class="fas fa-tag"></i> Apply New Discount Request</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.discount-approvals.store') }}" method="POST" class="row">
                            @csrf
                            <div class="col-md-3 mb-3">
                                <label for="student_id" class="form-label fw-bold">Select Student</label>
                                <select name="student_id" id="student_id" class="form-select select2" required>
                                    <option value="">Choose Student</option>
                                    @foreach($students as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }} (Adm No: {{ $st->admission_no }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="discount_rule_id" class="form-label fw-bold">Discount Rule</label>
                                <select name="discount_rule_id" id="discount_rule_id" class="form-select" required>
                                    <option value="">Choose Rule</option>
                                    @foreach($rules as $rule)
                                        <option value="{{ $rule->id }}">{{ $rule->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="fee_type_id" class="form-label fw-bold">Fee Type</label>
                                <select name="fee_type_id" id="fee_type_id" class="form-select" required>
                                    <option value="">Choose Type</option>
                                    @foreach($feeTypes as $ft)
                                        <option value="{{ $ft->id }}">{{ $ft->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="amount" class="form-label fw-bold">Amount (₹)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="month" class="form-label fw-bold">Month</label>
                                <select name="month" id="month" class="form-select" required>
                                    @foreach(['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'] as $m)
                                        <option value="{{ $m }}">{{ $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="hidden" name="academic_year" value="2026-27">
                            <div class="col-12 mt-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Discount Application</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Listings -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">All Discount Applications</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student</th>
                                    <th>Rule / Type</th>
                                    <th>Month (Year)</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Verification Details</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($approvals as $app)
                                    <tr>
                                        <td><strong>{{ $app->student->name }}</strong><br><small class="text-muted">Adm No: {{ $app->student->admission_no }}</small></td>
                                        <td>{{ $app->discountRule->name }}<br><small class="text-muted">{{ $app->feeType->name }}</small></td>
                                        <td>{{ $app->month }} ({{ $app->academic_year }})</td>
                                        <td class="fw-bold text-success">₹{{ number_format($app->amount, 2) }}</td>
                                        <td>
                                            @if($app->status === 'pending_verification')
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending Verification</span>
                                            @elseif($app->status === 'active')
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                            @else
                                                <span class="badge bg-danger"><i class="fas fa-times-circle"></i> {{ ucfirst($app->status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $app->creator->name ?? 'System' }}</td>
                                        <td>
                                            @if($app->verification_slip)
                                                <a href="{{ Storage::url($app->verification_slip) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-file-invoice"></i> View Slip
                                                </a>
                                                @if($app->verifier)
                                                    <br><small class="text-muted">Verified by: {{ $app->verifier->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">No slip uploaded</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($app->status === 'pending_verification')
                                                @if(auth()->user() && (auth()->user()->hasRole('accountant') || auth()->user()->role === 'accountant' || auth()->user()->hasRole('clerk') || auth()->user()->role === 'clerk'))
                                                    <form action="{{ route('admin.discount-approvals.verify', $app->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                                                        @csrf
                                                        <div class="input-group input-group-sm mb-1">
                                                            <input type="file" name="verification_slip" class="form-control" required accept="image/*,application/pdf">
                                                            <button class="btn btn-success" type="submit">Verify & Upload</button>
                                                        </div>
                                                    </form>
                                                @endif
                                                
                                                @if(auth()->user() && (auth()->user()->hasRole('admin') || auth()->user()->role === 'admin' || auth()->user()->hasRole('accountant') || auth()->user()->role === 'accountant'))
                                                    <form action="{{ route('admin.discount-approvals.reject', $app->id) }}" method="POST" class="d-inline-block">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this request?')">Reject</button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="text-muted">No Action Needed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No discount applications found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $approvals->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
