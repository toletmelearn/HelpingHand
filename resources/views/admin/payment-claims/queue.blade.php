@extends('layouts.admin')

@section('title', 'UPI Payment Matching Queue')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">UPI Payment Matching Queue</h4>
                <div class="page-title-right d-flex align-items-center gap-3">
                    <form method="POST" action="{{ route('admin.payment-claims.run-matching') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i>Run Matching
                        </button>
                    </form>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payment Claims</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-info shadow-sm" role="alert">
        Suggested matches (narration/reference or amount+date) require your one-click approval before a receipt is
        generated. Exact UTR+amount matches are auto-confirmed by the matching engine and never appear here.
    </div>

    <!-- Suggested Matches -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary fw-bold">Suggested Matches ({{ $suggested->count() }})</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Claim Amount</th>
                            <th>UTR / Slip</th>
                            <th>Bank Row</th>
                            <th>Confidence</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suggested as $claim)
                            <tr>
                                <td>{{ $claim->student->name ?? 'N/A' }}</td>
                                <td>₹{{ number_format($claim->amount, 2) }}</td>
                                <td>
                                    @if($claim->claim_type === 'bank_cash_deposit')
                                        <span class="badge bg-info text-dark mb-1">Bank Cash Deposit</span><br>
                                        <small class="text-muted">
                                            Branch: {{ $claim->branch }}<br>
                                            Deposited: {{ optional($claim->deposit_date)->format('Y-m-d') }}
                                        </small>
                                        @if($claim->screenshot_path)
                                            <br><a href="{{ asset('storage/' . $claim->screenshot_path) }}" target="_blank" class="d-inline-block mt-1">
                                                <img src="{{ asset('storage/' . $claim->screenshot_path) }}" alt="Deposit slip" style="max-width: 80px; max-height: 80px;" class="border rounded">
                                            </a>
                                        @endif
                                    @else
                                        {{ $claim->utr }}
                                    @endif
                                </td>
                                <td>
                                    ₹{{ number_format($claim->bankStatementRow->amount ?? 0, 2) }} on
                                    {{ optional($claim->bankStatementRow->transaction_date)->format('Y-m-d') }}
                                    @if($claim->bankStatementRow->branch ?? null)
                                        <br><small class="text-muted">Branch: {{ $claim->bankStatementRow->branch }}</small>
                                    @endif
                                    <br><small class="text-muted">{{ Str::limit($claim->bankStatementRow->narration, 60) }}</small>
                                </td>
                                <td><span class="badge bg-{{ $claim->match_confidence === 'narration' ? 'primary' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $claim->match_confidence)) }}</span></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <form method="POST" action="{{ route('admin.payment-claims.approve', $claim->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#rejectClaimModal"
                                                data-claim-id="{{ $claim->id }}">Reject</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No suggested matches pending review.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Unmatched Claims -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 text-primary fw-bold">Unmatched Claims</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Student</th><th>Amount</th><th>UTR</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse($unmatchedClaims as $claim)
                                    <tr>
                                        <td>{{ $claim->student->name ?? 'N/A' }}</td>
                                        <td>₹{{ number_format($claim->amount, 2) }}</td>
                                        <td>
                                            @if($claim->claim_type === 'bank_cash_deposit')
                                                <span class="badge bg-info text-dark">Cash Deposit</span> {{ $claim->branch }}
                                            @else
                                                {{ $claim->utr }}
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#manualMatchModal"
                                                    data-claim-id="{{ $claim->id }}"
                                                    data-claim-label="{{ $claim->student->name ?? 'N/A' }} - ₹{{ number_format($claim->amount, 2) }}">
                                                Pair with row
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-3 text-muted">None.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($unmatchedClaims->hasPages())
                        <div class="p-2">{{ $unmatchedClaims->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Unmatched Bank Rows -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 text-primary fw-bold">Unmatched Bank Statement Rows</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Date</th><th>Amount</th><th>UTR</th><th>Narration</th></tr>
                            </thead>
                            <tbody>
                                @forelse($unmatchedRows as $row)
                                    <tr>
                                        <td>{{ $row->transaction_date->format('Y-m-d') }}</td>
                                        <td>₹{{ number_format($row->amount, 2) }}</td>
                                        <td>{{ $row->utr ?: '—' }}</td>
                                        <td><small>{{ Str::limit($row->narration, 40) }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-3 text-muted">None.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($unmatchedRows->hasPages())
                        <div class="p-2">{{ $unmatchedRows->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject/Cancel Modal -->
<div class="modal fade" id="rejectClaimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rejectClaimForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject / Cancel Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason (required)</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manual Match Modal -->
<div class="modal fade" id="manualMatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="manualMatchForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Pair Claim &mdash; <span id="manualMatchClaimLabel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Bank Statement Row ID</label>
                    <input type="number" name="bank_statement_row_id" class="form-control" required>
                    <small class="text-muted">Find the matching row's ID from the "Unmatched Bank Statement Rows" table.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Match</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('rejectClaimModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const claimId = button.getAttribute('data-claim-id');
    document.getElementById('rejectClaimForm').action = '{{ url("admin/payment-claims") }}/' + claimId + '/reject';
});
document.getElementById('manualMatchModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const claimId = button.getAttribute('data-claim-id');
    document.getElementById('manualMatchClaimLabel').textContent = button.getAttribute('data-claim-label');
    document.getElementById('manualMatchForm').action = '{{ url("admin/payment-claims") }}/' + claimId + '/approve';
});
</script>
@endsection
