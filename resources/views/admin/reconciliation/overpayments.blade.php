@extends('layouts.admin')

@section('title', 'Reconciliation Center - Overpayments')

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
                    <a class="nav-link active fw-semibold" href="{{ route('admin.finance.reconciliation.overpayments') }}">
                        <i class="bi bi-wallet2-fill me-2"></i>Overpayments
                        <span class="badge bg-danger ms-1">{{ $students->total() }}</span>
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
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.mismatches') }}">
                        <i class="bi bi-exclamation-triangle me-2"></i>Ledger Mismatches
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

    <div class="alert alert-info shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i><strong>Overpayments Policy:</strong> Overpayments occur when a student's total credits posted exceed their total debits. This creates a negative outstanding balance which acts as a prepayment credit. Verify if these credits correspond to advanced payments or if a debit is missing.
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.finance.reconciliation.overpayments') }}" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by student name or admission number..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Search Students</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-wallet2 me-2"></i>Students with Credit Balance (Overpayments)</h5>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th class="text-end">Total Debit Charges</th>
                            <th class="text-end">Total Credit Payments</th>
                            <th class="text-end">Credit Balance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $student)
                            @php
                                // Fetch totals to display
                                $totals = DB::table('student_fee_ledgers')
                                    ->where('student_id', $student->id)
                                    ->selectRaw('SUM(debit) as debits, SUM(credit) as credits')
                                    ->first();
                                $debitVal = $totals->debits ?? 0.00;
                                $creditVal = $totals->credits ?? 0.00;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        @if(Route::has('admin.students.show'))
                                            <a href="{{ route('admin.students.show', $student->id) }}" class="text-decoration-none">{{ $student->name }}</a>
                                        @else
                                            {{ $student->name }}
                                        @endif
                                    </div>
                                    <small class="text-muted">Adm No: {{ $student->admission_no }}</small>
                                </td>
                                <td>{{ $student->schoolClass->name ?? 'N/A' }}</td>
                                <td class="text-end text-danger fw-semibold">₹{{ number_format($debitVal, 2) }}</td>
                                <td class="text-end text-success fw-semibold">₹{{ number_format($creditVal, 2) }}</td>
                                <td class="text-end text-success fw-bold font-size-15">
                                    ₹{{ number_format(abs($student->outstanding), 2) }}
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <form method="POST" action="{{ route('admin.finance.reconciliation.rebuild-ledger') }}">
                                            @csrf
                                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Recalculate Ledger Running Balances">
                                                <i class="bi bi-arrow-clockwise"></i> Rebuild Ledger
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal" data-bs-target="#issueRefundModal"
                                                data-student-id="{{ $student->id }}"
                                                data-student-name="{{ $student->name }}"
                                                data-max-amount="{{ number_format(abs($student->outstanding), 2, '.', '') }}"
                                                title="Issue Refund">
                                            <i class="bi bi-cash-coin"></i> Issue Refund
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-info-circle text-muted display-4 d-block mb-3"></i>
                                    <h5 class="text-muted">No student overpayments detected!</h5>
                                    <p class="text-muted mb-0">All students have outstanding balances greater than or equal to zero.</p>
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

<div class="modal fade" id="issueRefundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="issueRefundForm" method="POST" action="{{ route('admin.finance.reconciliation.issue-refund') }}">
                @csrf
                <input type="hidden" name="student_id" id="issueRefundStudentId">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Refund &mdash; <span id="issueRefundStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Overpaid balance: &#8377;<span id="issueRefundMaxAmount"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Amount to Refund</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="issueRefundAmount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Excess fee refund, caution money refund"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Issue Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('issueRefundModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('issueRefundStudentId').value = button.getAttribute('data-student-id');
    document.getElementById('issueRefundStudentName').textContent = button.getAttribute('data-student-name');
    const maxAmount = button.getAttribute('data-max-amount');
    document.getElementById('issueRefundMaxAmount').textContent = maxAmount;
    const amountInput = document.getElementById('issueRefundAmount');
    amountInput.max = maxAmount;
    amountInput.value = maxAmount;
});
</script>
@endsection
