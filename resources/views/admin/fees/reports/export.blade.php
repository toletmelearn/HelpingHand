<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap');
        body {
            font-family: 'Outfit', sans-serif;
            color: #334155;
            background-color: #ffffff;
            padding: 20px;
        }
        .report-header {
            border-bottom: 3px double #cbd5e1;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .table-export th {
            background-color: #f8fafc !important;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-export td {
            font-size: 0.85rem;
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Action bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print bg-light p-3 rounded">
            <span><strong>Print Preview Mode</strong></span>
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print / Save PDF</button>
        </div>

        <!-- Header -->
        <div class="report-header text-center">
            <h2 class="fw-bold text-dark mb-1">Helping Hand School</h2>
            <h4 class="text-danger fw-bold mb-2">{{ $title }}</h4>
            <p class="text-muted small mb-0">Generated on: {{ date('Y-m-d H:i:s') }}</p>
        </div>

        <!-- Data table -->
        <table class="table table-striped table-export">
            <thead>
                <tr>
                    @if($type === 'collection_register')
                        <th>Receipt No</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th class="text-end">Amount</th>
                        <th>Cashier</th>
                    @elseif($type === 'cash_book')
                        <th>Date</th>
                        <th>Reference/Receipt</th>
                        <th>Particulars</th>
                        <th class="text-end">Debit (Collection)</th>
                        <th class="text-end">Credit (Refund)</th>
                    @elseif($type === 'day_book')
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                    @elseif($type === 'ledger_book')
                        <th>Date</th>
                        <th>Reference Type</th>
                        <th>Reference ID</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Running Balance</th>
                    @elseif($type === 'outstanding_register')
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class Name</th>
                        <th class="text-end">Total Demanded</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-end">Outstanding Balance</th>
                    @elseif($type === 'demand_register')
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class Name</th>
                        <th>Demand Date</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    @elseif($type === 'refund_register')
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Refund Type</th>
                        <th class="text-end">Amount Refunded</th>
                        <th>Payment Mode</th>
                        <th>Reason</th>
                    @elseif($type === 'discount_register')
                        <th>Student Name</th>
                        <th>Class Name</th>
                        <th>Discount Rule Name</th>
                        <th class="text-end">Discount Amount</th>
                        <th>Status</th>
                    @elseif($type === 'late_fee_register')
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Class Name</th>
                        <th class="text-end">Late Fee Collected</th>
                    @elseif($type === 'head_wise')
                        <th>Fee Head Name</th>
                        <th class="text-end">Total Collected</th>
                    @elseif($type === 'month_wise')
                        <th>Month</th>
                        <th class="text-end">Cash</th>
                        <th class="text-end">UPI</th>
                        <th class="text-end">Bank Transfer</th>
                        <th class="text-end">Online</th>
                        <th class="text-end">Total Collected</th>
                    @elseif($type === 'class_wise')
                        <th>Class Name</th>
                        <th class="text-end">Total Collected</th>
                    @elseif($type === 'session_comparison')
                        <th>Academic Session</th>
                        <th class="text-end">Total Demand</th>
                        <th class="text-end">Total Collected</th>
                        <th class="text-end">Total Outstanding</th>
                        <th class="text-end">Recovery Rate (%)</th>
                    @elseif($type === 'income_summary')
                        <th>Component / Head</th>
                        <th class="text-end">Demanded Amount</th>
                        <th class="text-end">Collected Amount</th>
                        <th class="text-end">Outstanding Amount</th>
                    @elseif($type === 'cancelled_receipts')
                        <th>Receipt No</th>
                        <th>Student Name</th>
                        <th class="text-end">Original Amount</th>
                        <th>Reversed Date</th>
                        <th>Reason</th>
                        <th>Reversed By</th>
                    @elseif($type === 'receipt_register')
                        <th>Receipt No</th>
                        <th>Student Name</th>
                        <th>Payment Date</th>
                        <th>Payment Mode</th>
                        <th class="text-end">Paid Amount</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($data as $row)
                    <tr>
                        @if($type === 'collection_register')
                            <td>{{ $row->receipt_no }}</td>
                            <td>{{ $row->admission_no }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }}</td>
                            <td>{{ $row->payment_date }}</td>
                            <td>{{ strtoupper($row->payment_mode) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->final_amount, 2) }}</td>
                            <td>{{ $row->cashier_name }}</td>
                        @elseif($type === 'cash_book')
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->reference }}</td>
                            <td>{{ $row->particulars }}</td>
                            <td class="text-end">₹{{ number_format($row->debit, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->credit, 2) }}</td>
                        @elseif($type === 'day_book')
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-end">₹{{ number_format($row->debit, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->credit, 2) }}</td>
                        @elseif($type === 'ledger_book')
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->reference_type }}</td>
                            <td>{{ $row->reference_id }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-end">₹{{ number_format($row->debit, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->credit, 2) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->running_balance, 2) }}</td>
                        @elseif($type === 'outstanding_register')
                            <td>{{ $row->admission_no }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }}</td>
                            <td class="text-end">₹{{ number_format($row->total_charged, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->total_paid, 2) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->outstanding_balance, 2) }}</td>
                        @elseif($type === 'demand_register')
                            <td>{{ $row->admission_no }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }}</td>
                            <td>{{ $row->demand_date }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->amount, 2) }}</td>
                        @elseif($type === 'refund_register')
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ strtoupper($row->type) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->amount, 2) }}</td>
                            <td>{{ strtoupper($row->payment_mode) }}</td>
                            <td>{{ $row->reason }}</td>
                        @elseif($type === 'discount_register')
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }}</td>
                            <td>{{ $row->discount_name }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->amount, 2) }}</td>
                            <td>{{ strtoupper($row->status) }}</td>
                        @elseif($type === 'late_fee_register')
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->late_fee_collected, 2) }}</td>
                        @elseif($type === 'head_wise')
                            <td>{{ $row->head_name }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->total_collected, 2) }}</td>
                        @elseif($type === 'month_wise')
                            <td>{{ $row['month_name'] }}</td>
                            <td class="text-end">₹{{ number_format($row['cash'], 2) }}</td>
                            <td class="text-end">₹{{ number_format($row['upi'], 2) }}</td>
                            <td class="text-end">₹{{ number_format($row['bank'], 2) }}</td>
                            <td class="text-end">₹{{ number_format($row['online'], 2) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row['total'], 2) }}</td>
                        @elseif($type === 'class_wise')
                            <td>{{ $row->class_name }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->total_collected, 2) }}</td>
                        @elseif($type === 'session_comparison')
                            <td>{{ $row->academic_year }}</td>
                            <td class="text-end">₹{{ number_format($row->total_demand, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->total_collected, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->total_outstanding, 2) }}</td>
                            @php
                                $recovery = $row->total_demand > 0 ? round(($row->total_collected / $row->total_demand) * 100, 2) : 100.00;
                            @endphp
                            <td class="text-end fw-bold">{{ $recovery }}%</td>
                        @elseif($type === 'income_summary')
                            <td>{{ $row->head_name }}</td>
                            <td class="text-end">₹{{ number_format($row->demanded, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->collected, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->outstanding, 2) }}</td>
                        @elseif($type === 'cancelled_receipts')
                            <td>{{ $row->receipt_no }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td class="text-end">₹{{ number_format($row->original_amount, 2) }}</td>
                            <td>{{ $row->reversed_date }}</td>
                            <td>{{ $row->reason }}</td>
                            <td>{{ $row->approved_by_name }}</td>
                        @elseif($type === 'receipt_register')
                            <td>{{ $row->receipt_no }}</td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->payment_date }}</td>
                            <td>{{ strtoupper($row->payment_mode) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->final_amount, 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($format === 'print')
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    @endif
</body>
</html>
