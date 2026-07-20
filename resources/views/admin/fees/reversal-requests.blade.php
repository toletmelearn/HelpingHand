@extends('layouts.admin')

@section('title', 'Fee Reversal Requests')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Fee Reversal Requests</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Fee Reversal Requests</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Requests Table -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="card-title mb-0 text-white"><i class="fas fa-history me-2"></i> Pending Reversal Requests (Admin Governance Panel)</h5>
                    <span class="badge bg-warning text-dark">{{ $requests->count() }} Pending</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4" style="width: 25%;">Student Details</th>
                                    <th style="width: 25%;">Receipt Details & Currency Impact</th>
                                    <th style="width: 15%;">Requested By (Clerk)</th>
                                    <th style="width: 15%;">Reason for Reversal</th>
                                    <th style="width: 10%;">Submitted Date</th>
                                    <th class="pe-4 text-end" style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $req)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark">{{ $req->student->name ?? 'N/A' }}</div>
                                            <span class="badge bg-light text-secondary border">Adm No: {{ $req->student->admission_no ?? $req->student->reg_no ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            <div class="text-secondary fw-semibold">Receipt: <span class="text-primary">{{ $req->feeCollection->receipt_no ?? 'N/A' }}</span></div>
                                            <div class="fs-5 fw-bold text-danger">₹{{ number_format($req->feeCollection->final_amount ?? 0, 2) }}</div>
                                            <small class="text-muted d-block" style="font-size: 11px;">(This amount will be debited/credited back)</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2 d-none d-md-block">
                                                    <span class="avatar-title rounded-circle bg-soft-primary text-primary" style="padding: 4px 8px; background-color: #e0f2fe; color: #0369a1; font-weight: bold;">
                                                        {{ strtoupper(substr($req->requester->name ?? 'S', 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark">{{ $req->requester->name ?? 'System' }}</div>
                                                    <small class="text-muted">Role: Clerk</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-muted p-2 bg-light rounded border-start border-3 border-warning" style="font-size: 13px; max-width: 250px; word-wrap: break-word;">
                                                {{ $req->reason }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-dark">{{ $req->created_at->format('d-m-Y') }}</div>
                                            <small class="text-muted">{{ $req->created_at->format('h:i A') }}</small>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="d-flex gap-2 justify-content-end">
                                                <form action="{{ route('admin.fees.reversal-requests.approve', $req->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3 py-2" onclick="return confirm('Confirming will set target collection to Reversed and trigger a Ledger Service balance rebuild. Proceed?')">
                                                        <i class="fas fa-check-circle me-1"></i> Approve Reversal
                                                    </button>
                                                </form>
                                                
                                                <form action="{{ route('admin.fees.reversal-requests.reject', $req->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold px-2 py-2" onclick="return confirm('Are you sure you want to reject this clerk request?')">
                                                        <i class="fas fa-times-circle"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <div class="mb-2"><i class="fas fa-inbox fa-3x text-light"></i></div>
                                            <div>No pending fee reversal requests found. All system balances are reconciled.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($requests->hasPages())
                    <div class="p-3 border-top d-flex justify-content-end">
                        {{ $requests->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
