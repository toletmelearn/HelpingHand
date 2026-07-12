@extends('layouts.admin')

@section('title', 'Security Deposit Refunds')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">Security Deposit Refunds</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Security Deposits</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-info shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i><strong>Policy:</strong> A deposit moves to "Refund Pending" automatically when a student is marked Passed Out or a Transfer Certificate is published. Refunds always require manual review here -- nothing is returned automatically.
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-9">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach(['held' => 'Held', 'refund_pending' => 'Refund Pending', 'refunded' => 'Refunded', 'adjusted' => 'Adjusted'] as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-shield-lock me-2"></i>Deposits</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Deposit Type</th>
                            <th class="text-end">Amount Held</th>
                            <th class="text-end">Refund Amount</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $deposit)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $deposit->student->name ?? 'N/A' }}</div>
                                    <small class="text-muted">Adm No: {{ $deposit->student->admission_no ?? '' }}</small>
                                </td>
                                <td>{{ $deposit->feeType->name ?? 'Security Deposit' }}</td>
                                <td class="text-end">₹{{ number_format($deposit->amount, 2) }}</td>
                                <td class="text-end">
                                    {{ $deposit->refund_amount !== null ? '₹' . number_format($deposit->refund_amount, 2) : '—' }}
                                </td>
                                <td>
                                    @php
                                        $badgeClass = ['held' => 'secondary', 'refund_pending' => 'warning', 'refunded' => 'success', 'adjusted' => 'info'][$deposit->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $deposit->status)) }}</span>
                                </td>
                                <td class="text-center">
                                    @if($deposit->status === 'refund_pending')
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal" data-bs-target="#resolveDepositModal"
                                                data-deposit-id="{{ $deposit->id }}"
                                                data-student-name="{{ $deposit->student->name ?? 'N/A' }}"
                                                data-refund-amount="{{ number_format($deposit->refund_amount, 2, '.', '') }}"
                                                title="Resolve Refund">
                                            <i class="bi bi-cash-coin"></i> Resolve
                                        </button>
                                    @else
                                        <span class="text-muted small">No action needed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-info-circle text-muted display-4 d-block mb-3"></i>
                                    <h5 class="text-muted">No security deposits found.</h5>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($deposits->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $deposits->firstItem() }} to {{ $deposits->lastItem() }} of {{ $deposits->total() }} deposits
                    </div>
                    <div>{{ $deposits->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="resolveDepositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="resolveDepositForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Deposit Refund &mdash; <span id="resolveDepositStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Calculated refundable amount: &#8377;<span id="resolveDepositAmount"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select name="action" id="resolveDepositAction" class="form-select" required>
                            <option value="refund">Refund (pay out in cash/bank)</option>
                            <option value="adjust">Adjust against outstanding dues</option>
                        </select>
                        <small class="text-muted">Refund requires the student's outstanding dues to already be cleared.</small>
                    </div>
                    <div id="resolveDepositRefundFields">
                        <div class="mb-3">
                            <label class="form-label">Payment Mode</label>
                            <select name="payment_mode" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refund Reference</label>
                            <input type="text" name="refund_ref" class="form-control" placeholder="Cheque no. / UTR / transaction ref">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('resolveDepositModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const depositId = button.getAttribute('data-deposit-id');
    document.getElementById('resolveDepositForm').action = '{{ url("admin/security-deposits") }}/' + depositId + '/resolve';
    document.getElementById('resolveDepositStudentName').textContent = button.getAttribute('data-student-name');
    document.getElementById('resolveDepositAmount').textContent = button.getAttribute('data-refund-amount');
});

document.getElementById('resolveDepositAction').addEventListener('change', function () {
    const refundFields = document.getElementById('resolveDepositRefundFields');
    refundFields.style.display = this.value === 'refund' ? '' : 'none';
});
</script>
@endsection
