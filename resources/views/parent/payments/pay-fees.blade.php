@extends('layouts.parent')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Online Fee Payments</h1>
            <p class="mb-4">Review pending tuition installments for <strong>{{ $student->name }}</strong> and checkout securely online.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($pendingClaims->isNotEmpty())
        <div class="row justify-content-center mb-3">
            <div class="col-md-10">
                <div class="alert alert-info">
                    <strong>Under verification:</strong> you've submitted {{ $pendingClaims->count() }} UPI payment(s) awaiting confirmation.
                    Your dues will update automatically once matched against the bank statement.
                </div>
            </div>
        </div>
    @endif

    @if(count($pendingFees) > 0)
        <div class="row justify-content-center mb-4">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Scan to Pay via UPI</h6>
                    </div>
                    <div class="card-body">
                        <div id="upiQrLoading">
                            <button type="button" id="showUpiQrBtn" class="btn btn-success">
                                <i class="fab fa-google-pay me-1"></i> Show UPI QR
                            </button>
                        </div>
                        <div id="upiQrArea" class="d-none">
                            <div class="row">
                                <div class="col-md-4 text-center mb-3">
                                    <img id="upiQrImage" src="" alt="UPI QR Code" style="width: 200px; height: 200px;">
                                    <p class="text-muted small mt-2">Amount: ₹<span id="upiQrAmount"></span></p>
                                </div>
                                <div class="col-md-8">
                                    <p class="text-muted small">After paying, enter the 12-digit UTR / transaction reference number from your banking app below.</p>
                                    <form id="submitClaimForm" method="POST" action="{{ route('parent.payments.submit-claim') }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="reference_token" id="claimReferenceToken">
                                        <input type="hidden" name="amount" id="claimAmount">
                                        <div class="mb-3">
                                            <label class="form-label">UTR / Transaction Reference Number</label>
                                            <input type="text" name="utr" class="form-control" maxlength="12" minlength="12" pattern="[0-9]{12}" placeholder="12-digit UTR" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Payment Screenshot (optional)</label>
                                            <input type="file" name="screenshot" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Submit UTR</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div id="upiQrError" class="alert alert-warning d-none mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pending Tuition Installments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Fee Structure</th>
                                    <th>Total Charge</th>
                                    <th>Amount Paid</th>
                                    <th>Remaining Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingFees as $fee)
                                    <tr>
                                        <td><strong>{{ $fee['name'] }}</strong></td>
                                        <td>₹{{ number_format($fee['total_amount'], 2) }}</td>
                                        <td>₹{{ number_format($fee['paid_amount'], 2) }}</td>
                                        <td><strong class="text-danger">₹{{ number_format($fee['balance'], 2) }}</strong></td>
                                        <td>
                                            <span class="text-muted small">Use "Scan to Pay via UPI" above</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-success font-weight-bold">
                                            All tuition fee installments have been completely paid! No balance outstanding.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(count($pendingFees) > 0)
<script>
document.getElementById('showUpiQrBtn')?.addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Loading...';

    fetch('{{ route('parent.payments.upi-qr') }}')
        .then(response => response.json())
        .then(data => {
            const errorBox = document.getElementById('upiQrError');
            if (!data.status) {
                errorBox.textContent = data.message;
                errorBox.classList.remove('d-none');
                btn.disabled = false;
                btn.textContent = 'Show UPI QR';
                return;
            }

            document.getElementById('upiQrImage').src = 'data:image/svg+xml;base64,' + data.qr_code;
            document.getElementById('upiQrAmount').textContent = Number(data.amount).toFixed(2);
            document.getElementById('claimReferenceToken').value = data.reference_token;
            document.getElementById('claimAmount').value = data.amount;
            document.getElementById('upiQrLoading').classList.add('d-none');
            document.getElementById('upiQrArea').classList.remove('d-none');
        })
        .catch(() => {
            const errorBox = document.getElementById('upiQrError');
            errorBox.textContent = 'Could not load the UPI QR. Please try again.';
            errorBox.classList.remove('d-none');
            btn.disabled = false;
            btn.textContent = 'Show UPI QR';
        });
});
</script>
@endif
@endsection
