@extends('layouts.admin')

@section('title', 'Payment Info')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">Payment Info &mdash; QR &amp; Bank Details</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payment Info</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @unless($vpa)
        <div class="alert alert-warning shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            No UPI VPA is configured yet. Set one under
            <a href="{{ route('admin.configurations.index') }}">Admin Configuration &rarr; Fee Management &rarr; School UPI VPA</a>
            before printing this page for a notice board.
        </div>
    @endunless

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm" id="printable-payment-info">
                <div class="card-body text-center py-5">
                    <h4 class="mb-1">{{ $schoolName }}</h4>
                    <p class="text-muted mb-4">Scan to Pay Fees via UPI</p>

                    @if($qr)
                        <img src="data:image/svg+xml;base64,{{ $qr['qr_code'] }}" alt="UPI QR Code" style="width: 220px; height: 220px;">
                        <p class="text-muted small mt-3 mb-4">{{ $vpa }}</p>
                    @else
                        <div class="text-muted py-5">QR unavailable until a UPI VPA is configured.</div>
                    @endif

                    <hr class="my-4">

                    <h6 class="text-uppercase text-muted small mb-3">Or Pay via Bank Transfer</h6>
                    <table class="table table-borderless w-auto mx-auto text-start">
                        <tr>
                            <th class="pe-3">Account Name</th>
                            <td>{{ $bank['account_name'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th class="pe-3">Account Number</th>
                            <td>{{ $bank['account_number'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th class="pe-3">IFSC Code</th>
                            <td>{{ $bank['ifsc'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th class="pe-3">Bank &amp; Branch</th>
                            <td>{{ $bank['bank_name'] ?: '—' }}</td>
                        </tr>
                    </table>

                    <p class="text-muted small mt-4 mb-0">
                        After paying, submit the UTR/transaction reference number from the Parent Portal &mdash;
                        Pay Fees page so your payment can be matched and receipted.
                    </p>
                </div>
            </div>

            <div class="text-center mt-3 d-print-none">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print for Notice Board
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
