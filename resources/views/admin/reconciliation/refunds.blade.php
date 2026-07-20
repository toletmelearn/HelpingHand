@extends('layouts.admin')

@section('title', 'Reconciliation Center - Refund Audits')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">Finance Reconciliation Center</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reconciliation</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills card-header-pills bg-light p-2 rounded shadow-sm">
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.unresolved') }}">
                        <i class="bi bi-question-circle me-2"></i>Unresolved Records
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.overpayments') }}">
                        <i class="bi bi-wallet2 me-2"></i>Overpayments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="{{ route('admin.finance.reconciliation.refunds') }}">
                        <i class="bi bi-arrow-left-right-fill me-2"></i>Refunds & Reversals
                        <span class="badge bg-danger ms-1">{{ $refunds->total() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.orphans') }}">
                        <i class="bi bi-node-minus me-2"></i>Orphan References
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.mismatches') }}">
                        <i class="bi bi-exclamation-triangle me-2"></i>Ledger Mismatches
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.finance.reconciliation.refunds') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Search Student</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by student name or admission number..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="refund" {{ request('type') == 'refund' ? 'selected' : '' }}>Refund</option>
                        <option value="reversal" {{ request('type') == 'reversal' ? 'selected' : '' }}>Reversal</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill me-2"></i>Filter Audits</button>
                    @if(request()->filled('search') || request()->filled('type'))
                        <a href="{{ route('admin.finance.reconciliation.refunds') }}" class="btn btn-outline-secondary ms-2"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Refund & Reversal Audit Trail</h5>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Processed At</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th>Payment Mode</th>
                            <th>Reason / Description</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refunds as $refund)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($refund->processed_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $refund->student->name ?? 'N/A' }}</div>
                                    <small class="text-muted">Adm No: {{ $refund->student->admission_no ?? 'N/A' }}</small>
                                </td>
                                <td>{{ $refund->student->schoolClass->name ?? 'N/A' }}</td>
                                <td>
                                    @if($refund->type === 'refund')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-reply-fill me-1"></i>Refund</span>
                                    @else
                                        <span class="badge bg-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Reversal</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($refund->amount, 2) }}</td>
                                <td>
                                    <span class="text-muted"><i class="bi bi-credit-card-2-front me-1"></i>{{ str_replace('_', ' ', $refund->payment_mode) }}</span>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 250px;" title="{{ $refund->reason }}">
                                        {{ $refund->reason ?? '-' }}
                                    </span>
                                </td>
                                <td>{{ $refund->processedBy->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-shield-check text-success display-4 d-block mb-3"></i>
                                    <h5 class="text-muted">No refund or reversal transactions found!</h5>
                                    <p class="text-muted mb-0">All collection audits are currently reconciled.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($refunds->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $refunds->firstItem() }} to {{ $refunds->lastItem() }} of {{ $refunds->total() }} transactions
                    </div>
                    <div>
                        {{ $refunds->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
