@extends('layouts.admin')

@section('title', 'Closing Report Details')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .report-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        .card-premium {
            box-shadow: none !important;
            border: none !important;
        }
        body {
            background: #fff;
            color: #000;
        }
    }
</style>

<div class="container-fluid py-4 report-container">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <a href="{{ route('admin.fees.cashier-closings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Closing Registry</a>
        </div>
        <div>
            <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>
        </div>
    </div>

    <div class="card card-premium p-4">
        <div class="text-center mb-4">
            <h2>{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'School Management System') }}</h2>
            <h4>CASHIER SHIFT CLOSING REPORT</h4>
            <p class="text-muted">Closing Date: {{ $closing->closing_date->format('F d, Y') }}</p>
        </div>

        <div class="row g-3 mb-4 border-bottom pb-3">
            <div class="col-md-4">
                <strong>Cashier:</strong> {{ $closing->cashier->name ?? 'N/A' }}
            </div>
            <div class="col-md-4">
                <strong>Submission Status:</strong> <span class="badge bg-success">{{ ucfirst($closing->status) }}</span>
            </div>
            <div class="col-md-4">
                <strong>Opening Balance:</strong> ₹{{ number_format($closing->opening_balance, 2) }}
            </div>
        </div>

        <h5 class="mb-3 border-bottom pb-2">Mode wise Summary</h5>
        
        <table class="table table-bordered align-middle">
            <thead>
                <tr class="table-light">
                    <th>Payment Mode</th>
                    <th class="text-end">Expected (System)</th>
                    <th class="text-end">Actual Counted</th>
                    <th class="text-end">Difference</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $modes = [
                        'Cash' => [$closing->expected_cash, $closing->actual_cash],
                        'UPI' => [$closing->expected_upi, $closing->actual_upi],
                        'Bank' => [$closing->expected_bank, $closing->actual_bank],
                        'Cheque' => [$closing->expected_cheque, $closing->actual_cheque],
                        'Online' => [$closing->expected_online, $closing->actual_online]
                    ];
                    $totalExpected = 0;
                    $totalActual = 0;
                @endphp
                @foreach($modes as $label => $vals)
                    @php
                        $exp = (float)$vals[0];
                        $act = (float)$vals[1];
                        $diff = $act - $exp;
                        $totalExpected += $exp;
                        $totalActual += $act;
                    @endphp
                    <tr>
                        <td><strong>{{ $label }}</strong></td>
                        <td class="text-end">₹{{ number_format($exp, 2) }}</td>
                        <td class="text-end">₹{{ number_format($act, 2) }}</td>
                        <td class="text-end fw-bold {{ $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : 'text-muted') }}">
                            ₹{{ number_format($diff, 2) }}
                        </td>
                    </tr>
                @endforeach
                <tr class="table-light fw-bold">
                    <td>TOTALS</td>
                    <td class="text-end">₹{{ number_format($totalExpected, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totalActual, 2) }}</td>
                    <td class="text-end fw-bold {{ ($totalActual - $totalExpected) < 0 ? 'text-danger' : (($totalActual - $totalExpected) > 0 ? 'text-success' : 'text-muted') }}">
                        ₹{{ number_format($totalActual - $totalExpected, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        @if($closing->discrepancy_reason)
            <div class="alert alert-warning mb-4">
                <strong><i class="bi bi-exclamation-triangle"></i> Discrepancy Note:</strong>
                <p class="mb-0 mt-1">{{ $closing->discrepancy_reason }}</p>
            </div>
        @endif

        @if($closing->remarks)
            <div class="mb-4">
                <strong>Closing Notes / Remarks:</strong>
                <p class="text-muted mt-1">{{ $closing->remarks }}</p>
            </div>
        @endif

        <div class="row mt-5 pt-4">
            <div class="col-6 text-center">
                <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;" class="pt-2">
                    Cashier Signature
                </div>
            </div>
            <div class="col-6 text-center">
                <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto;" class="pt-2">
                    Verified By (Accountant)
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
