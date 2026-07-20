<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Daily Collection Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #000;
            padding: 20px;
        }
        
        .table th {
            background-color: #f8fafc !important;
            color: #000 !important;
            font-weight: 600;
            border-bottom: 2px solid #000 !important;
        }
        
        .totals-row {
            font-weight: bold;
            background-color: #f1f5f9 !important;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="mb-0">Daily Collection Register Print Preview</h4>
        <div>
            <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer"></i> Print Now</button>
            <button onclick="window.close();" class="btn btn-secondary">Close Window</button>
        </div>
    </div>

    <div class="text-center mb-4">
        <h2>{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'School Management System') }}</h2>
        <h4>DAILY COLLECTION REGISTER</h4>
        <p class="text-muted">Report Period: {{ request('start_date', now()->format('Y-m-d')) }} to {{ request('end_date', now()->format('Y-m-d')) }}</p>
    </div>

    <div class="mb-3 small">
        <strong>Filters Applied:</strong>
        @if(request('cashier_id')) Cashier: {{ \App\Models\User::find(request('cashier_id'))->name ?? 'N/A' }} | @endif
        @if(request('payment_mode')) Mode: {{ request('payment_mode') }} | @endif
        Grouping: Grouped by {{ ucfirst($groupBy) }}
    </div>

    <table class="table table-bordered table-striped align-middle">
        <thead>
            @if($groupBy === 'cashier')
                <tr>
                    <th>Cashier</th>
                    <th class="text-end">Cash</th>
                    <th class="text-end">UPI</th>
                    <th class="text-end">Bank</th>
                    <th class="text-end">Cheque</th>
                    <th class="text-end">Online</th>
                    <th class="text-end">Refund</th>
                    <th class="text-end">Cancelled</th>
                    <th class="text-end">Net Collection</th>
                </tr>
            @elseif($groupBy === 'date')
                <tr>
                    <th>Date</th>
                    <th class="text-end">Cash</th>
                    <th class="text-end">UPI</th>
                    <th class="text-end">Bank</th>
                    <th class="text-end">Cheque</th>
                    <th class="text-end">Online</th>
                    <th class="text-end">Refund</th>
                    <th class="text-end">Cancelled</th>
                    <th class="text-end">Net Collection</th>
                </tr>
            @elseif($groupBy === 'payment_mode')
                <tr>
                    <th>Payment Mode</th>
                    <th class="text-center">Transaction Count</th>
                    <th class="text-end">Total Collection</th>
                </tr>
            @else
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Cashier</th>
                    <th>Payment Mode</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Status</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($records as $row)
                @if($groupBy === 'cashier')
                    <tr>
                        <td>{{ $row->cashier_name }}</td>
                        <td class="text-end">₹{{ number_format($row->cash, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->upi, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->bank, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->cheque, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->online, 2) }}</td>
                        <td class="text-end text-danger">₹{{ number_format($row->refund, 2) }}</td>
                        <td class="text-end text-muted">₹{{ number_format($row->cancelled, 2) }}</td>
                        <td class="text-end fw-bold">₹{{ number_format($row->net_collection, 2) }}</td>
                    </tr>
                @elseif($groupBy === 'date')
                    <tr>
                        <td>{{ $row->group_date }}</td>
                        <td class="text-end">₹{{ number_format($row->cash, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->upi, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->bank, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->cheque, 2) }}</td>
                        <td class="text-end">₹{{ number_format($row->online, 2) }}</td>
                        <td class="text-end text-danger">₹{{ number_format($row->refund, 2) }}</td>
                        <td class="text-end text-muted">₹{{ number_format($row->cancelled, 2) }}</td>
                        <td class="text-end fw-bold">₹{{ number_format($row->net_collection, 2) }}</td>
                    </tr>
                @elseif($groupBy === 'payment_mode')
                    <tr>
                        <td>{{ $row->payment_mode }}</td>
                        <td class="text-center">{{ $row->tx_count }}</td>
                        <td class="text-end fw-bold text-success">₹{{ number_format($row->cash_total, 2) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row->receipt_no }}</td>
                        <td>{{ $row->payment_date }}</td>
                        <td>{{ $row->student_name }}</td>
                        <td>{{ $row->cashier_name }}</td>
                        <td>{{ $row->payment_mode }}</td>
                        <td class="text-end">₹{{ number_format($row->final_amount, 2) }}</td>
                        <td class="text-center">{{ $row->deleted_at ? 'Cancelled' : 'Active' }}</td>
                    </tr>
                @endif
            @endforeach
            
            @if(($groupBy === 'cashier' || $groupBy === 'date') && $totals)
                <tr class="totals-row">
                    <td>GRAND TOTALS</td>
                    <td class="text-end">₹{{ number_format($totals->total_cash, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_upi, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_bank, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_cheque, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_online, 2) }}</td>
                    <td class="text-end text-danger">₹{{ number_format($totals->total_refund, 2) }}</td>
                    <td class="text-end text-muted">₹{{ number_format($totals->total_cancelled, 2) }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($totals->net_collection, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
