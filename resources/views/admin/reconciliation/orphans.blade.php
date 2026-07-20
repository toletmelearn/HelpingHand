@extends('layouts.admin')

@section('title', 'Reconciliation Center - Orphan References')

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
                    <a class="nav-link active fw-semibold" href="{{ route('admin.finance.reconciliation.orphans') }}">
                        <i class="bi bi-node-minus-fill me-2"></i>Orphan References
                        <span class="badge bg-danger ms-1">{{ $records->total() }}</span>
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
            <form method="GET" action="{{ route('admin.finance.reconciliation.orphans') }}" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by student name or admission number..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Search Orphans</button>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-warning shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Integrity Warning:</strong> Orphan ledger entries occur when a fee item, collection, or refund is hard-deleted but its corresponding ledger entry remains. Review these records to ensure financial integrity.
    </div>

    <!-- Main Content Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-node-minus me-2"></i>Orphaned Ledger Entries</h5>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Broken Reference Connection</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $record->student->name ?? 'N/A' }}</div>
                                    <small class="text-muted">Adm No: {{ $record->student->admission_no ?? 'N/A' }}</small>
                                </td>
                                <td>{{ $record->student->schoolClass->name ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($record->date)->format('Y-m-d') }}</td>
                                <td>{{ $record->description }}</td>
                                <td>
                                    <span class="badge bg-danger text-uppercase font-size-11">{{ str_replace('_', ' ', $record->reference_type) }}</span>
                                    <span class="text-muted font-size-12">ID: {{ $record->reference_id }} (Not found)</span>
                                </td>
                                <td class="text-end text-danger fw-semibold">
                                    {{ $record->debit > 0 ? '₹' . number_format($record->debit, 2) : '-' }}
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    {{ $record->credit > 0 ? '₹' . number_format($record->credit, 2) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-shield-check text-success display-4 d-block mb-3"></i>
                                    <h5 class="text-muted">No orphaned references found!</h5>
                                    <p class="text-muted mb-0">All ledger references point to valid records in the database.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($records->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} records
                    </div>
                    <div>
                        {{ $records->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
