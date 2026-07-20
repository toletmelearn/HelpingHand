<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Collection Register</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h2 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: #1a365d;
        }
        
        .header p {
            margin: 0;
            color: #666;
            font-size: 11px;
        }
        
        .meta-info {
            margin-bottom: 15px;
            font-size: 10px;
            color: #555;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .table-register {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table-register th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-weight: bold;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        
        .table-register td {
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-size: 10px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-row {
            background-color: #eff6ff;
            font-weight: bold;
            color: #1e3a8a;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>DAILY COLLECTION REGISTER</h2>
        <p>Grouping: Grouped by {{ ucfirst($groupBy) }}</p>
        <p>Report Period: {{ request('start_date', now()->format('Y-m-d')) }} to {{ request('end_date', now()->format('Y-m-d')) }}</p>
    </div>
    
    <div class="meta-info">
        <strong>Filters Applied:</strong>
        @if(request('cashier_id')) Cashier: {{ \App\Models\User::find(request('cashier_id'))->name ?? 'N/A' }} | @endif
        @if(request('payment_mode')) Mode: {{ request('payment_mode') }} | @endif
        Generated on {{ now()->format('F d, Y h:i A') }}
    </div>

    <table class="table-register">
        <thead>
            @if($groupBy === 'cashier')
                <tr>
                    <th>Cashier</th>
                    <th class="text-right">Cash</th>
                    <th class="text-right">UPI</th>
                    <th class="text-right">Bank</th>
                    <th class="text-right">Cheque</th>
                    <th class="text-right">Online</th>
                    <th class="text-right">Refund</th>
                    <th class="text-right">Cancelled</th>
                    <th class="text-right">Net Collection</th>
                </tr>
            @elseif($groupBy === 'date')
                <tr>
                    <th>Date</th>
                    <th class="text-right">Cash</th>
                    <th class="text-right">UPI</th>
                    <th class="text-right">Bank</th>
                    <th class="text-right">Cheque</th>
                    <th class="text-right">Online</th>
                    <th class="text-right">Refund</th>
                    <th class="text-right">Cancelled</th>
                    <th class="text-right">Net Collection</th>
                </tr>
            @elseif($groupBy === 'payment_mode')
                <tr>
                    <th>Payment Mode</th>
                    <th class="text-center">Transaction Count</th>
                    <th class="text-right">Total Collection</th>
                </tr>
            @else
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Cashier</th>
                    <th>Payment Mode</th>
                    <th class="text-right">Amount</th>
                    <th class="text-center">Status</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($records as $row)
                @if($groupBy === 'cashier')
                    <tr>
                        <td><strong>{{ $row->cashier_name }}</strong></td>
                        <td class="text-right">₹{{ number_format($row->cash, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->upi, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->bank, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->cheque, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->online, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->refund, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->cancelled, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->net_collection, 2) }}</td>
                    </tr>
                @elseif($groupBy === 'date')
                    <tr>
                        <td><strong>{{ $row->group_date }}</strong></td>
                        <td class="text-right">₹{{ number_format($row->cash, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->upi, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->bank, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->cheque, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->online, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->refund, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->cancelled, 2) }}</td>
                        <td class="text-right">₹{{ number_format($row->net_collection, 2) }}</td>
                    </tr>
                @elseif($groupBy === 'payment_mode')
                    <tr>
                        <td><strong>{{ $row->payment_mode }}</strong></td>
                        <td class="text-center">{{ $row->tx_count }}</td>
                        <td class="text-right">₹{{ number_format($row->cash_total, 2) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row->receipt_no }}</td>
                        <td>{{ $row->payment_date }}</td>
                        <td>{{ $row->student_name }}</td>
                        <td>{{ $row->cashier_name }}</td>
                        <td>{{ $row->payment_mode }}</td>
                        <td class="text-right">₹{{ number_format($row->final_amount, 2) }}</td>
                        <td class="text-center">
                            @if($row->deleted_at)
                                <span class="badge badge-cancelled">Cancelled</span>
                            @else
                                <span class="badge badge-active">Active</span>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
            
            @if(($groupBy === 'cashier' || $groupBy === 'date') && $totals)
                <tr class="totals-row">
                    <td>GRAND TOTALS</td>
                    <td class="text-right">₹{{ number_format($totals->total_cash, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_upi, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_bank, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_cheque, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_online, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_refund, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_cancelled, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->net_collection, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
