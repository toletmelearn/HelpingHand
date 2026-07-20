<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Statement - {{ $student->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
        }
        .school-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0 0 5px 0;
        }
        .school-subtitle {
            font-size: 11px;
            color: #666;
            margin: 0;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .student-details {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .student-details td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .student-details .label {
            color: #666;
            width: 20%;
            font-weight: bold;
        }
        .student-details .val {
            width: 30%;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .summary-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .summary-table td {
            font-size: 12px;
            font-weight: bold;
        }
        .outstanding-box {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .timeline-table th, .timeline-table td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            font-size: 10px;
        }
        .timeline-table th {
            background-color: #374151;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .text-center {
            text-align: center;
        }
        .text-end {
            text-align: right;
        }
        .fw-bold {
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .signature-section {
            margin-top: 50px;
            width: 100%;
        }
        .signature-box {
            width: 200px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-title">HELPINGHAND SCHOOL ERP</div>
        <div class="school-subtitle">Finance & Account Management Division</div>
    </div>

    <div class="title">STUDENT FINANCIAL STATEMENT</div>

    <table class="student-details">
        <tr>
            <td class="label">Student Name:</td>
            <td class="val"><strong>{{ $student->name }}</strong></td>
            <td class="label">Account No:</td>
            <td class="val"><strong>{{ $student->financialAccount->account_no ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Admission No:</td>
            <td class="val">{{ $student->admission_no ?: 'N/A' }}</td>
            <td class="label">Status:</td>
            <td class="val">{{ ucfirst($student->financialAccount->status ?? 'active') }}</td>
        </tr>
        <tr>
            <td class="label">Class/Section:</td>
            <td class="val">{{ $student->schoolClass->name ?? $student->class }} (Section: {{ $student->section ?: 'N/A' }})</td>
            <td class="label">Statement Date:</td>
            <td class="val">{{ now()->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <table class="summary-table">
        <thead>
            <tr>
                <th>Opening Bal</th>
                <th>Charges</th>
                <th>Discounts</th>
                <th>Scholarships</th>
                <th>Late Fees</th>
                <th>Payments</th>
                <th>Refunds</th>
                <th class="outstanding-box">Outstanding Due</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>₹{{ number_format($cards['opening_balance'], 2) }}</td>
                <td>₹{{ number_format($cards['total_charges'], 2) }}</td>
                <td>₹{{ number_format($cards['total_discounts'], 2) }}</td>
                <td>₹{{ number_format($cards['total_scholarships'], 2) }}</td>
                <td>₹{{ number_format($cards['total_late_fees'], 2) }}</td>
                <td>₹{{ number_format($cards['total_payments'], 2) }}</td>
                <td>₹{{ number_format($cards['total_refunds'], 2) }}</td>
                <td class="outstanding-box">₹{{ number_format($cards['outstanding_balance'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="fw-bold" style="margin-bottom: 8px; font-size: 11px; text-transform: uppercase;">Transaction History Ledger:</div>
    <table class="timeline-table">
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 15%;">Voucher Type</th>
                <th style="width: 15%;">Ref No</th>
                <th>Description</th>
                <th style="width: 12%;">Debit (₹)</th>
                <th style="width: 12%;">Credit (₹)</th>
                <th style="width: 14%;">Balance (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ledger as $entry)
                @php
                    $rBal = $runningBalances[$entry->id] ?? 0.00;
                @endphp
                <tr>
                    <td class="text-center">{{ $entry->date ? $entry->date->format('Y-m-d') : 'N/A' }}</td>
                    <td class="text-center">{{ ucwords(str_replace('_', ' ', $entry->reference_type)) }}</td>
                    <td class="text-center">{{ $entry->reference_id }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="text-end" style="color: #dc2626;">
                        {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '0.00' }}
                    </td>
                    <td class="text-end" style="color: #16a34a;">
                        {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '0.00' }}
                    </td>
                    <td class="text-end fw-bold">
                        ₹{{ number_format($rBal, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No transaction records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature-section">
        <tr>
            <td>
                <div class="signature-box" style="margin-top: 40px;">
                    Accountant Signature
                </div>
            </td>
            <td style="text-align: right;">
                <div class="signature-box" style="margin-top: 40px; float: right;">
                    Authorized Signature
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        This is a system-generated statement and does not require a physical signature.
    </div>
</body>
</html>
