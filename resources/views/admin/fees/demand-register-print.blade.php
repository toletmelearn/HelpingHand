<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Fee Demand Register</title>
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
        <h4 class="mb-0">Fee Demand Register Print Preview</h4>
        <div>
            <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer"></i> Print Now</button>
            <button onclick="window.close();" class="btn btn-secondary">Close Window</button>
        </div>
    </div>

    <div class="text-center mb-4">
        <h2>{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'School Management System') }}</h2>
        <h4>FEE DEMAND REGISTER</h4>
        <p class="text-muted">Generated on {{ now()->format('Y-m-d h:i A') }}</p>
    </div>

    <div class="mb-3 small">
        <strong>Filters Applied:</strong>
        @if(request('class_id')) Class: {{ \App\Models\SchoolClass::find(request('class_id'))->name ?? 'N/A' }} | @endif
        @if(request('section_id')) Section: {{ \App\Models\Section::find(request('section_id'))->name ?? 'N/A' }} | @endif
        @if(request('session')) Session: {{ request('session') }} | @endif
        @if(request('month')) Month: {{ date('F', mktime(0, 0, 0, request('month'), 1)) }} | @endif
        @if(request('fee_head_id')) Fee Head: {{ \App\Models\FeeType::find(request('fee_head_id'))->name ?? 'N/A' }} | @endif
        @if(request('status')) Status: {{ ucfirst(request('status')) }} @else All Records @endif
    </div>

    <table class="table table-bordered table-striped align-middle">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Student</th>
                <th>Class</th>
                <th class="text-end">Fee Demand</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Late Fee</th>
                <th class="text-end">Collected</th>
                <th class="text-end">Refund</th>
                <th class="text-end">Outstanding</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $row)
                @php
                    $outstanding = (float) $row->outstanding;
                    $collected = (float) $row->collected;
                @endphp
                <tr>
                    <td>{{ $row->admission_no }}</td>
                    <td>{{ $row->student_name }}</td>
                    <td>{{ $row->class_name }} @if($row->section_name) ({{ $row->section_name }}) @endif</td>
                    <td class="text-end">₹{{ number_format($row->fee_demand, 2) }}</td>
                    <td class="text-end">₹{{ number_format($row->discount, 2) }}</td>
                    <td class="text-end">₹{{ number_format($row->late_fee, 2) }}</td>
                    <td class="text-end">₹{{ number_format($row->collected, 2) }}</td>
                    <td class="text-end">₹{{ number_format($row->refund, 2) }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($row->outstanding, 2) }}</td>
                    <td class="text-center">
                        @if($outstanding <= 0 && $collected > 0)
                            Paid
                        @elseif($outstanding > 0 && $collected == 0)
                            Unpaid
                        @elseif($outstanding > 0 && $collected > 0)
                            Partial
                        @elseif($outstanding < 0)
                            Overpaid
                        @else
                            No Due
                        @endif
                    </td>
                </tr>
            @endforeach
            
            @if($totals)
                <tr class="totals-row">
                    <td colspan="3">GRAND TOTALS ({{ count($records) }} Students)</td>
                    <td class="text-end">₹{{ number_format($totals->total_demand ?? 0, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_discount ?? 0, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_late_fee ?? 0, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_collected ?? 0, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_refund ?? 0, 2) }}</td>
                    <td class="text-end">₹{{ number_format($totals->total_outstanding ?? 0, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <script>
        // Auto trigger print when page is ready (if not coming from print-action cancel)
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
