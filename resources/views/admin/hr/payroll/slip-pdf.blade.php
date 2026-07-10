<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salary Slip - {{ $salary->teacher->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3f51b5;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #3f51b5;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        .info-section {
            width: 100%;
            margin-bottom: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .info-table td.label {
            color: #666;
            font-weight: bold;
            width: 20%;
        }
        .info-table td.value {
            color: #111;
            width: 30%;
        }
        .salary-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .salary-details th {
            background-color: #3f51b5;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #3f51b5;
        }
        .salary-details td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .salary-details tr.total-row td {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .summary-box {
            background-color: #e8eaf6;
            border: 1px solid #c5cae9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 40px;
        }
        .summary-box table {
            width: 100%;
        }
        .summary-box td {
            padding: 5px 0;
        }
        .summary-box td.label {
            font-size: 14px;
            color: #3f51b5;
            font-weight: bold;
        }
        .summary-box td.value {
            font-size: 18px;
            color: #1a237e;
            font-weight: bold;
            text-align: right;
        }
        .footer {
            margin-top: 50px;
            width: 100%;
        }
        .signature-table {
            width: 100%;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
        }
        .signature-line {
            width: 180px;
            border-bottom: 1px solid #666;
            margin: 50px auto 10px auto;
        }
    </style>
</head>
<body>

    <div class="header" style="border-bottom: 2px solid #3f51b5; padding-bottom: 15px; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr style="border: none;">
                @php
                    $schoolLogo = \App\Models\AdminConfiguration::get('general', 'school_logo');
                @endphp
                @if($schoolLogo && file_exists(public_path('storage/' . $schoolLogo)))
                <td style="width: 12%; vertical-align: middle; padding-right: 15px; border: none;">
                    <img src="{{ public_path('storage/' . $schoolLogo) }}" alt="Logo" style="max-height: 60px; max-width: 80px; display: block;">
                </td>
                @endif
                <td style="vertical-align: middle; text-align: left; border: none;">
                    <h1 style="margin: 0; font-size: 20px; color: #3f51b5; font-weight: bold;">{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HELPINGHAND PUBLIC SCHOOL') }}</h1>
                    <p style="margin: 3px 0 0 0; color: #666; font-size: 11px;">
                        {{ \App\Models\AdminConfiguration::get('general', 'school_address', '123 Education Street, City Name, State - 123456') }}
                    </p>
                    <p style="margin: 2px 0 0 0; color: #666; font-size: 11px;">
                        Phone: {{ \App\Models\AdminConfiguration::get('general', 'school_phone', '+91-1234567890') }} | Email: {{ \App\Models\AdminConfiguration::get('general', 'school_email', 'info@helpinghand.edu.in') }}
                    </p>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: middle; border: none;">
                    <h2 style="margin: 0; font-size: 16px; color: #333; font-weight: bold; text-transform: uppercase;">SALARY PAYSLIP</h2>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="label">Employee Name:</td>
                <td class="value">{{ $salary->teacher->name }}</td>
                <td class="label">Pay Period:</td>
                <td class="value">
                    @if($salary->pay_month && $salary->pay_year)
                        {{ \Carbon\Carbon::create($salary->pay_year, $salary->pay_month, 1)->format('F Y') }}
                    @else
                        &mdash;
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Employee ID:</td>
                <td class="value">{{ $salary->teacher->employee_id ?? 'N/A' }}</td>
                <td class="label">Pay Date:</td>
                <td class="value">{{ $salary->payment_date ? $salary->payment_date->format('d M Y') : now()->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="label">Pay Scale:</td>
                <td class="value">{{ $salary->pay_scale ?? 'Standard Scale' }}</td>
                <td class="label">Payment Method:</td>
                <td class="value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $salary->payment_method) }}</td>
            </tr>
            <tr>
                <td class="label">Bank A/C No:</td>
                <td class="value">{{ $salary->teacher->bank_account_number ?? 'N/A' }}</td>
                <td class="label">Reference No:</td>
                <td class="value">{{ $salary->reference_number ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">IFSC Code:</td>
                <td class="value">{{ $salary->teacher->ifsc_code ?? 'N/A' }}</td>
                <td class="label">PAN:</td>
                <td class="value">{{ $salary->teacher->pan_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">UAN Number:</td>
                <td class="value">{{ $salary->teacher->uan_number ?? 'N/A' }}</td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
        </table>
    </div>

    <table class="salary-details">
        <thead>
            <tr>
                <th style="width: 50%;">Earnings & Allowances</th>
                <th style="width: 50%;">Deductions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="border: none; padding: 4px 0;">Basic Salary:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->basic_salary, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">HRA:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->hra, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">DA:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->da, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">TA:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->ta, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">Medical Allowance:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->medical_allowance, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">Special Allowance:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->special_allowance, 2) }}</td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="border: none; padding: 4px 0;">Provident Fund (PF):</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->pf_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">ESI:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->esi_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">TDS/Tax:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->tax_deduction, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 0;">Other Deductions:</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->other_deductions, 2) }}</td>
                        </tr>
                        @if($salary->attendance_deduction_days > 0)
                        <tr>
                            <td style="border: none; padding: 4px 0;">Attendance Deduction ({{ rtrim(rtrim(number_format($salary->attendance_deduction_days, 2), '0'), '.') }} day(s)):</td>
                            <td style="border: none; padding: 4px 0; text-align: right;">₹{{ number_format($salary->attendance_deduction_amount, 2) }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
            <tr class="total-row">
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="border: none; padding: 0;">Gross Earnings:</td>
                            <td style="border: none; padding: 0; text-align: right;">₹{{ number_format($salary->gross_salary, 2) }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="border: none; padding: 0;">Total Deductions:</td>
                            <td style="border: none; padding: 0; text-align: right;">₹{{ number_format($salary->pf_amount + $salary->esi_amount + $salary->tax_deduction + $salary->other_deductions + ($salary->attendance_deduction_amount ?? 0), 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="summary-box">
        <table>
            <tr>
                <td class="label">NET SALARY PAYOUT:</td>
                <td class="value">₹{{ number_format($salary->net_salary, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <p style="margin: 0; color: #666;">Employee Signature</p>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <p style="margin: 0; color: #666;">Authorized Signatory</p>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
