<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Demand Register</title>
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
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-partial {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-overpaid {
            background-color: #e0f2fe;
            color: #075985;
        }
        
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 9px;
            color: #888;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>FEE DEMAND REGISTER</h2>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>
    
    <div class="meta-info">
        <strong>Filters Applied:</strong>
        @if(request('class_id')) Class: {{ \App\Models\SchoolClass::find(request('class_id'))->name ?? 'N/A' }} | @endif
        @if(request('section_id')) Section: {{ \App\Models\Section::find(request('section_id'))->name ?? 'N/A' }} | @endif
        @if(request('session')) Session: {{ request('session') }} | @endif
        @if(request('month')) Month: {{ date('F', mktime(0, 0, 0, request('month'), 1)) }} | @endif
        @if(request('fee_head_id')) Fee Head: {{ \App\Models\FeeType::find(request('fee_head_id'))->name ?? 'N/A' }} | @endif
        @if(request('status')) Status: {{ ucfirst(request('status')) }} @else All Records @endif
    </div>

    <table class="table-register">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Student</th>
                <th>Class</th>
                <th class="text-right">Fee Demand</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Late Fee</th>
                <th class="text-right">Collected</th>
                <th class="text-right">Refund</th>
                <th class="text-right">Outstanding</th>
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
                    <td class="text-right">₹{{ number_format($row->fee_demand, 2) }}</td>
                    <td class="text-right">₹{{ number_format($row->discount, 2) }}</td>
                    <td class="text-right">₹{{ number_format($row->late_fee, 2) }}</td>
                    <td class="text-right">₹{{ number_format($row->collected, 2) }}</td>
                    <td class="text-right">₹{{ number_format($row->refund, 2) }}</td>
                    <td class="text-right">₹{{ number_format($row->outstanding, 2) }}</td>
                    <td class="text-center">
                        @if($outstanding <= 0 && $collected > 0)
                            <span class="badge badge-paid">Paid</span>
                        @elseif($outstanding > 0 && $collected == 0)
                            <span class="badge badge-unpaid">Unpaid</span>
                        @elseif($outstanding > 0 && $collected > 0)
                            <span class="badge badge-partial">Partial</span>
                        @elseif($outstanding < 0)
                            <span class="badge badge-overpaid">Overpaid</span>
                        @else
                            <span class="badge bg-light">No Due</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            
            @if($totals)
                <tr class="totals-row">
                    <td colspan="3">GRAND TOTALS ({{ count($records) }} Students)</td>
                    <td class="text-right">₹{{ number_format($totals->total_demand ?? 0, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_discount ?? 0, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_late_fee ?? 0, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_collected ?? 0, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_refund ?? 0, 2) }}</td>
                    <td class="text-right">₹{{ number_format($totals->total_outstanding ?? 0, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
    
    <div class="footer">
        Page 1 of 1 | HelpingHand Finance Management System
    </div>

</body>
</html>
