@extends('layouts.admin')

@section('title', 'Reconciliation Center - Balance Mismatches')

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
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.refunds') }}">
                        <i class="bi bi-arrow-left-right me-2"></i>Refunds & Reversals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.orphans') }}">
                        <i class="bi bi-node-minus me-2"></i>Orphan References
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="{{ route('admin.finance.reconciliation.mismatches') }}">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Ledger Mismatches
                        <span class="badge bg-danger ms-1">{{ $students->total() }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-danger shadow-sm" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i><strong>Critical Ledger Errors:</strong> A balance mismatch indicates that a student's cumulative ledger balance `SUM(debit) - SUM(credit)` does not match the stored `running_balance` on their latest ledger record. Rebuilding the ledger running balances chronologically will resolve this mismatch.
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.finance.reconciliation.mismatches') }}" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by student name or admission number..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Search Mismatches</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Ledger Balance Calculation Mismatches</h5>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th class="text-end">Cumulative Sum: SUM(debit) - SUM(credit)</th>
                            <th class="text-end">Last Stored Stated Balance</th>
                            <th class="text-end">Variance (Error)</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $student)
                            @php
                                $calculated = (float)$student->calculated_balance;
                                $stated = (float)$student->latest_running_balance;
                                $variance = abs($calculated - $stated);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $student->name }}</div>
                                    <small class="text-muted">Adm No: {{ $student->admission_no }}</small>
                                </td>
                                <td>{{ $student->schoolClass->name ?? 'N/A' }}</td>
                                <td class="text-end fw-semibold">₹{{ number_format($calculated, 2) }}</td>
                                <td class="text-end fw-semibold">₹{{ number_format($stated, 2) }}</td>
                                <td class="text-end text-danger fw-bold">₹{{ number_format($variance, 2) }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.finance.reconciliation.rebuild-ledger') }}">
                                        @csrf
                                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                                        <button type="submit" class="btn btn-sm btn-danger px-3 shadow-sm">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Rebuild Ledger
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-check-circle text-success display-4 d-block mb-3"></i>
                                    <h5 class="text-muted">No ledger balance mismatches detected!</h5>
                                    <p class="text-muted mb-0">All student ledger trails are mathematically sound and perfectly reconciled.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($students->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students
                    </div>
                    <div>
                        {{ $students->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
