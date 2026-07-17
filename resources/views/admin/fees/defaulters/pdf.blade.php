<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Defaulter Registry</title>
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

        .totals-row {
            background-color: #eff6ff;
            font-weight: bold;
            color: #1e3a8a;
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
        <h2>DEFAULTER REGISTRY</h2>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="meta-info">
        <strong>Filters Applied:</strong>
        @if(request('class_id')) Class: {{ \App\Models\SchoolClass::find(request('class_id'))->name ?? 'N/A' }} | @endif
        @if(request('section_id')) Section: {{ \App\Models\Section::find(request('section_id'))->name ?? 'N/A' }} | @endif
        @if(request('stage')) Stage: {{ request('stage') }} | @endif
        @if(request('month')) Month: {{ date('F', mktime(0, 0, 0, (int) request('month'), 1)) }} | @endif
        @if(request('quarter')) Quarter: {{ request('quarter') }} | @endif
        @if(request('ageing')) Ageing: {{ str_replace('_', '-', request('ageing')) }} days | @endif
        @if(request('min_amount')) Min Outstanding: ₹{{ number_format((float) request('min_amount'), 2) }} | @endif
        @unless(request()->anyFilled(['class_id', 'section_id', 'stage', 'month', 'quarter', 'ageing', 'min_amount'])) All Records @endunless
    </div>

    <table class="table-register">
        <thead>
            <tr>
                <th>Admission No</th>
                <th>Student</th>
                <th>Class</th>
                <th>Section</th>
                <th>Workflow Stage</th>
                <th class="text-right">Outstanding</th>
                <th>Last Action Date</th>
            </tr>
        </thead>
        <tbody>
            @php $totalOutstanding = 0; @endphp
            @foreach($defaulters as $def)
                @php $totalOutstanding += (float) $def->outstanding_amount; @endphp
                <tr>
                    <td>{{ $def->student->admission_no ?? '' }}</td>
                    <td>{{ $def->student->name ?? '' }}</td>
                    <td>{{ $def->student->schoolClass->name ?? 'N/A' }}</td>
                    <td>{{ $def->student->section->name ?? 'N/A' }}</td>
                    <td>{{ $def->stage }}</td>
                    <td class="text-right">₹{{ number_format($def->outstanding_amount, 2) }}</td>
                    <td>{{ $def->last_action_date ? $def->last_action_date->format('Y-m-d H:i') : 'No action yet' }}</td>
                </tr>
            @endforeach

            <tr class="totals-row">
                <td colspan="5">GRAND TOTAL ({{ count($defaulters) }} Students)</td>
                <td class="text-right">₹{{ number_format($totalOutstanding, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        HelpingHand Finance Management System
    </div>

</body>
</html>
