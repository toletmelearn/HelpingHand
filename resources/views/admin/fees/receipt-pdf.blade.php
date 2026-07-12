<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt - {{ $feeCollection->receipt_no }}</title>
    <style>
        @page {
            margin: 15px 20px;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.5px;
            color: #334155;
            line-height: 1.35;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .brand-bar {
            height: 3px;
            background-color: #1e3a8a;
            margin-bottom: 12px;
        }
        .receipt-container {
            width: 100%;
            padding: 5px;
            background-color: #ffffff;
        }
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: 0;
            margin: 0;
            padding: 0;
        }
        .layout-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        
        /* Header Section */
        .school-name {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 2px;
        }
        .school-info {
            font-size: 8px;
            color: #475569;
        }
        .receipt-title-box {
            text-align: right;
        }
        .receipt-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .receipt-subtitle {
            font-size: 8px;
            color: #64748b;
        }
        
        .divider {
            border-bottom: 1px solid #e2e8f0;
            margin: 8px 0;
        }
        
        /* Details Section */
        .details-table {
            margin-bottom: 10px;
        }
        .card-cell {
            border: 1px solid #e2e8f0;
            border-left: 2.5px solid #1e3a8a;
            background-color: #f8fafc;
            padding: 6px 8px;
            border-radius: 2px;
        }
        .card-title {
            font-size: 8px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        .info-row {
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            color: #64748b;
            width: 80px;
            display: inline-block;
        }
        .info-value {
            color: #0f172a;
            display: inline-block;
        }
        
        /* Table Details */
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .fee-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 4px 8px;
            border: 1px solid #1e3a8a;
        }
        .fee-table td {
            padding: 5px 8px;
            font-size: 8px;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            color: #334155;
        }
        .fee-table tr.even-row td {
            background-color: #f8fafc;
        }
        .amount-cell {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        
        /* Summary Wrapper */
        .summary-wrapper {
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .payment-meta {
            border: 1px solid #e2e8f0;
            border-left: 2.5px solid #475569;
            background-color: #f8fafc;
            padding: 6px 8px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 2px 0;
            font-size: 8px;
            color: #475569;
        }
        .summary-table tr.total-row td {
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
            background-color: #1e3a8a;
            padding: 4px 6px;
            border-top: 1px solid #1e3a8a;
        }
        .summary-table tr.total-row .amount-val {
            color: #ffffff;
            font-size: 11px;
            text-align: right;
        }
        
        .qr-section {
            border: 1px dashed #cbd5e1;
            padding: 5px;
            background-color: #fafafa;
            text-align: center;
        }
        
        /* Signature Section */
        .signature-section {
            margin-top: 15px;
        }
        .signature-box {
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            color: #475569;
        }
        .signature-line {
            width: 110px;
            margin: 20px auto 4px auto;
            border-top: 1px solid #94a3b8;
        }
        .footer-note {
            text-align: center;
            margin-top: 10px;
            font-size: 7px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    @php
        $totals = \App\Services\FinanceCalculationService::getReceiptTotals($feeCollection);
        $recurringTotal = $totals['recurringTotal'];
        $oneTimeTotal = $totals['oneTimeTotal'];
        $totalYearlyFee = $totals['totalYearlyFee'];
        $totalPaid = $totals['totalPaidTillNow'];
        $remainingRecurring = $totals['remainingRecurring'];
        $remainingOneTime = $totals['remainingOneTime'];
        $pendingFee = $totals['remainingFee'];
    @endphp
    
    <div class="brand-bar"></div>
    
    <div class="receipt-container">
        <!-- Header -->
        <table class="layout-table" style="margin-bottom: 10px;">
            <tr>
                @php
                    $schoolLogo = \App\Models\AdminConfiguration::get('general', 'school_logo');
                @endphp
                @if($schoolLogo && file_exists(public_path('storage/' . $schoolLogo)))
                <td style="width: 12%; vertical-align: middle; padding-right: 12px;">
                    <img src="{{ public_path('storage/' . $schoolLogo) }}" alt="Logo" style="max-height: 50px; max-width: 70px; display: block;">
                </td>
                @endif
                
                <td style="width: @if($schoolLogo && file_exists(public_path('storage/' . $schoolLogo))) 58% @else 70% @endif; vertical-align: middle;">
                    <div class="school-name">{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HELPINGHAND PUBLIC SCHOOL') }}</div>
                    <div class="school-info">{{ \App\Models\AdminConfiguration::get('general', 'school_address', '123 Education Street, City Name, State - 123456') }}</div>
                    <div class="school-info">Phone: {{ \App\Models\AdminConfiguration::get('general', 'school_phone', '+91-1234567890') }} | Email: {{ \App\Models\AdminConfiguration::get('general', 'school_email', 'info@helpinghand.edu.in') }}</div>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: middle;">
                    <div class="receipt-title">FEE RECEIPT</div>
                    <div class="receipt-subtitle">Receipt No: <strong>{{ $feeCollection->receipt_no }}</strong></div>
                    <div class="receipt-subtitle">Date: {{ $feeCollection->payment_date->format('d-m-Y') }}</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Student and Receipt Info -->
        <table class="layout-table details-table" style="margin-bottom: 20px;">
            <tr>
                <td style="width: 48%;" class="card-cell">
                    <div class="card-title">Student Details</div>
                    @php
                        $studentPhoto = $feeCollection->student->photo && file_exists(public_path('storage/' . $feeCollection->student->photo))
                            ? public_path('storage/' . $feeCollection->student->photo)
                            : public_path('images/default-avatar.png');
                    @endphp
                    <img src="{{ $studentPhoto }}" alt="Student Photo" style="width: 40px; height: 40px; object-fit: cover; float: right; border: 1px solid #cbd5e1;">
                    <div class="info-row">
                        <span class="info-label">Student Name:</span>
                        <span class="info-value" style="font-weight: bold;">{{ $feeCollection->student->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Class:</span>
                        <span class="info-value">{{ $feeCollection->student->schoolClass->name ?? $feeCollection->student->class_name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission No:</span>
                        <span class="info-value">{{ $feeCollection->student->admission_no ?? $feeCollection->student->reg_no ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Father's Name:</span>
                        <span class="info-value">{{ $feeCollection->student->father_name ?? $feeCollection->student->guardian_name ?? 'N/A' }}</span>
                    </div>
                </td>
                <td style="width: 4%;">&nbsp;</td>
                <td style="width: 48%;" class="card-cell">
                    <div class="card-title">Payment Details</div>
                    <div class="info-row">
                        <span class="info-label">Receipt No:</span>
                        <span class="info-value" style="font-weight: bold;">{{ $feeCollection->receipt_no }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Date:</span>
                        <span class="info-value">{{ $feeCollection->payment_date->format('d-m-Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Time:</span>
                        <span class="info-value">{{ $feeCollection->created_at->format('h:i A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Mode:</span>
                        <span class="info-value">{{ ucfirst($feeCollection->payment_mode) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fee Month(s):</span>
                        <span class="info-value" style="font-weight: bold; color: #15803d;">{{ is_array($feeCollection->fee_month) ? implode(', ', $feeCollection->fee_month) : ($feeCollection->fee_month ?? 'N/A') }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Fee Items Table -->
        <table class="fee-table">
            <thead>
                <tr>
                    <th style="width: 8%; text-align: center;">S.No</th>
                    <th style="width: 32%; text-align: left;">Fee Type</th>
                    <th style="width: 40%; text-align: left;">Description</th>
                    <th style="width: 20%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($feeCollection->feeCollectionItems as $index => $item)
                <tr class="{{ $index % 2 === 1 ? 'even-row' : '' }}">
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="font-weight: bold;">{{ $item->feeType->name }}</td>
                    <td>{{ $item->feeType->description ?? 'Fee Payment' }}</td>
                    <td class="amount-cell">₹{{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @php
            $paymentQr = \App\Models\AdminConfiguration::get('general', 'payment_qr');
            $hasQr = $paymentQr && file_exists(public_path('storage/' . $paymentQr));
        @endphp
        <!-- Summary & Payment Info / QR -->
        <table class="layout-table summary-wrapper">
            <tr>
                <!-- Left Column: Payment Info -->
                <td style="width: @if($hasQr) 45% @else 55% @endif; vertical-align: top;" class="payment-meta">
                    <div class="card-title">Collection Meta</div>
                    <div class="info-row">
                        <span class="info-label" style="width: 100px;">Collected By:</span>
                        <span class="info-value">{{ $feeCollection->collectedBy->name ?? 'System' }}</span>
                    </div>
                    @if(strtolower($feeCollection->payment_mode) !== 'cash' || $feeCollection->transaction_id)
                    <div class="info-row">
                        <span class="info-label" style="width: 100px;">Transaction ID:</span>
                        <span class="info-value">{{ $feeCollection->transaction_id ?? 'N/A' }}</span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-label" style="width: 100px;">Remarks:</span>
                        <span class="info-value">{{ $feeCollection->remarks ?? 'None' }}</span>
                    </div>
                </td>

                <!-- Middle Column: Optional QR Code -->
                @if($hasQr)
                <td style="width: 5%;">&nbsp;</td>
                <td style="width: 20%; vertical-align: top;">
                    <div class="qr-section">
                        <img src="{{ public_path('storage/' . $paymentQr) }}" alt="Payment QR" style="width: 60px; height: 60px; display: block; margin: 0 auto 4px auto;">
                        <div style="font-size: 7px; font-weight: bold; color: #475569;">Scan to Pay / Verify</div>
                    </div>
                </td>
                @endif

                <td style="width: 5%;">&nbsp;</td>

                <!-- Right Column: Totals Summary -->
                <td style="width: @if($hasQr) 25% @else 35% @endif; vertical-align: top;">
                    <table class="summary-table">
                        <tr>
                            <td style="text-align: left; padding: 1px 0; font-size: 10px; color: #475569;">Recurring Fee (Annual):</td>
                            <td style="text-align: right; padding: 1px 0; font-size: 10px; color: #475569;">₹{{ number_format($recurringTotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0; font-size: 10px; color: #475569;">One-time Fee:</td>
                            <td style="text-align: right; padding: 1px 0; font-size: 10px; color: #475569;">₹{{ number_format($oneTimeTotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 2px 0; font-weight: bold; color: #0f172a;">Total Yearly Fee:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #0f172a;">₹{{ number_format($totalYearlyFee, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 2px 0; font-weight: bold; color: #16a34a;">Paid Till Now:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #16a34a;">₹{{ number_format($totalPaid, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0; font-size: 10px; color: #64748b; padding-left: 5px;">• Rem. Recurring:</td>
                            <td style="text-align: right; padding: 1px 0; font-size: 10px; color: #64748b;">₹{{ number_format($remainingRecurring, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0; font-size: 10px; color: #64748b; padding-left: 5px;">• Rem. One-time:</td>
                            <td style="text-align: right; padding: 1px 0; font-size: 10px; color: #64748b;">₹{{ number_format($remainingOneTime, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 2px 0; font-weight: bold; color: #dc2626;">Remaining Fee:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #dc2626;">₹{{ number_format(max(0, $pendingFee), 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border-top: 1px dashed #cbd5e1; padding: 3px 0;"></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 2px 0;">Current Payment:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #0f172a;">₹{{ number_format($feeCollection->total_amount, 2) }}</td>
                        </tr>
                        @if($feeCollection->discount > 0)
                        <tr>
                            <td style="text-align: left; padding: 2px 0; color: #dc2626;">Discount:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #dc2626;">-₹{{ number_format($feeCollection->discount, 2) }}</td>
                        </tr>
                        @endif
                        @if($feeCollection->late_fine > 0)
                        <tr>
                            <td style="text-align: left; padding: 2px 0; color: #16a34a;">Late Fine:</td>
                            <td style="text-align: right; padding: 2px 0; font-weight: bold; color: #16a34a;">+₹{{ number_format($feeCollection->late_fine, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="total-row">
                            <td style="text-align: left;">Final Amount Paid:</td>
                            <td class="amount-val">₹{{ number_format($feeCollection->final_amount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Signatures Section -->
        <table class="layout-table signature-section">
            <tr>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>Student / Guardian</div>
                    </div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>Accountant</div>
                    </div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>Principal</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Disclaimer Footer -->
        <div class="footer-note">
            Thank you for your payment. This is an official computer-generated receipt of {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HELPINGHAND PUBLIC SCHOOL') }} and does not require a physical signature.
        </div>
    </div>
</body>
</html>