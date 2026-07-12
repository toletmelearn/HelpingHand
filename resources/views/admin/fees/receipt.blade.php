<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - {{ $feeCollection->receipt_no }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 40px 20px;
            background-color: #f1f5f9;
            color: #334155;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        /* Layout classes for toggling size */
        .receipt-container.layout-a4 {
            max-width: 800px;
        }
        .receipt-container.layout-a5 {
            max-width: 580px;
            padding: 15px;
            font-size: 12px;
        }
        .receipt-container.layout-a5 .school-name {
            font-size: 18px;
        }
        .receipt-container.layout-a5 .receipt-title {
            font-size: 16px;
        }
        .receipt-container.layout-a5 .fee-table td, .receipt-container.layout-a5 .fee-table th {
            padding: 8px;
        }

        /* Print Media Queries */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .action-buttons {
                display: none !important;
            }
            
            /* Apply specific print layout rules */
            .receipt-container.layout-a4 {
                width: 210mm;
                height: 297mm;
                padding: 15mm !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
            }
            
            .receipt-container.layout-a5 {
                width: 148mm;
                height: 210mm;
                padding: 10mm !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                font-size: 10px !important;
            }
            .receipt-container.layout-a5 .school-name {
                font-size: 14px !important;
            }
            .receipt-container.layout-a5 .school-address {
                font-size: 8px !important;
            }
            .receipt-container.layout-a5 .receipt-title {
                font-size: 12px !important;
                margin-top: 5px !important;
                padding-top: 5px !important;
            }
            .receipt-container.layout-a5 .details-grid {
                padding: 10px !important;
                gap: 5px !important;
            }
            .receipt-container.layout-a5 .fee-table th, .receipt-container.layout-a5 .fee-table td {
                padding: 5px !important;
                font-size: 10px !important;
            }
            .receipt-container.layout-a5 .summary-section {
                padding: 10px !important;
                margin: 10px 0 !important;
            }
            .receipt-container.layout-a5 .signature-section {
                margin-top: 10px !important;
            }
        }

        .receipt-header {
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-section img {
            max-height: 70px;
            max-width: 120px;
            object-fit: contain;
            border-radius: 4px;
            background: white;
            padding: 4px;
            border: 1px solid #e2e8f0;
        }
        .school-info {
            flex-grow: 1;
            margin-left: 20px;
        }
        .school-name {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }
        .school-address {
            font-size: 13px;
            color: #64748b;
            margin: 2px 0;
        }
        .receipt-info {
            text-align: right;
            font-size: 13px;
            color: #334155;
        }
        .info-item {
            margin: 4px 0;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            margin-top: 15px;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
        }
        .student-details {
            margin-bottom: 25px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
        }
        .detail-item {
            display: flex;
            font-size: 13px;
        }
        .detail-label {
            font-weight: bold;
            color: #64748b;
            width: 130px;
        }
        .detail-value {
            color: #0f172a;
            font-weight: 600;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .fee-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            padding: 10px 12px;
            border: 1px solid #1e293b;
        }
        .fee-table td {
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            padding: 12px;
            font-size: 13px;
            color: #334155;
        }
        .amount-cell {
            text-align: right;
            font-weight: 600;
        }
        .summary-section {
            margin: 25px 0;
            padding: 20px;
            background-color: #f8fafc;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 6px 0;
            font-size: 13px;
            color: #475569;
        }
        .summary-divider {
            border-top: 1px solid #cbd5e1;
            margin: 12px 0;
        }
        .summary-total {
            font-weight: bold;
            font-size: 16px;
            color: #0f172a;
            border-top: 1.5px solid #0f172a;
            padding-top: 12px;
            margin-top: 8px;
        }
        .payment-details {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            margin: 6px 0;
            font-size: 13px;
        }
        .info-label {
            width: 130px;
            font-weight: bold;
            color: #64748b;
        }
        .info-value {
            color: #0f172a;
            font-weight: 500;
        }
        .qr-code {
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            background-color: #fafafa;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            font-size: 11px;
            color: #64748b;
        }
        .qr-code img {
            width: 80px;
            height: 80px;
            margin-bottom: 5px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 45px;
            padding: 0 10px;
        }
        .signature-box {
            text-align: center;
            width: 150px;
            font-size: 12px;
            font-weight: bold;
            color: #475569;
        }
        .signature-line {
            border-top: 1.5px solid #94a3b8;
            margin: 35px 0 8px 0;
        }
        .footer-section {
            text-align: center;
            margin-top: 20px;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 2px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        @media print {
            .action-buttons {
                display: none !important;
            }
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                color: #000;
                font-size: 9px;
                line-height: 1.3;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 8px 10px;
                max-width: 100%;
                margin: 0;
            }
            .receipt-header {
                padding-bottom: 5px;
                margin-bottom: 10px;
                border-bottom-width: 1px;
            }
            .school-name {
                font-size: 15px;
            }
            .school-address {
                font-size: 9px;
            }
            .receipt-info {
                font-size: 9px;
            }
            .receipt-title {
                font-size: 13px;
                margin-top: 5px;
                padding-top: 5px;
            }
            .details-grid {
                padding: 8px 12px;
                gap: 6px;
                margin-bottom: 10px;
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .detail-item {
                font-size: 9px;
            }
            .detail-label {
                width: 90px;
            }
            .fee-table {
                margin: 10px 0;
            }
            .fee-table th {
                padding: 5px 8px;
                font-size: 9px;
                background-color: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .fee-table td {
                padding: 5px 8px;
                font-size: 9px;
            }
            .summary-section {
                margin: 10px 0;
                padding: 8px 12px;
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .summary-row {
                font-size: 9px;
                margin: 2px 0;
            }
            .summary-divider {
                margin: 6px 0;
            }
            .summary-total {
                font-size: 11px;
                padding-top: 6px;
                margin-top: 4px;
            }
            .payment-details {
                margin-top: 10px;
                padding-top: 8px;
            }
            .info-row {
                font-size: 9px;
                margin: 2px 0;
            }
            .info-label {
                width: 90px;
            }
            .qr-code {
                margin: 10px 0;
                padding: 6px;
                background-color: #fafafa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .qr-code img {
                width: 50px;
                height: 50px;
            }
            .signature-section {
                margin-top: 15px;
            }
            .signature-box {
                font-size: 9px;
                width: 100px;
            }
            .signature-line {
                margin: 15px 0 4px 0;
            }
            .footer-section {
                margin-top: 10px;
                padding-top: 5px;
                font-size: 8px;
            }
        }
    </style>
</head>
<body>
    @php
        $schoolName = \App\Models\AdminConfiguration::get('general', 'school_name', 'HELPINGHAND PUBLIC SCHOOL');
        $schoolAddress = \App\Models\AdminConfiguration::get('general', 'school_address', '123 Education Street, City Name, State - 123456');
        $schoolPhone = \App\Models\AdminConfiguration::get('general', 'school_phone', '+91-1234567890');
        $schoolEmail = \App\Models\AdminConfiguration::get('general', 'school_email', 'info@helpinghand.edu.in');
        $schoolLogo = \App\Models\AdminConfiguration::get('general', 'school_logo');

        // Formulate WhatsApp receipt confirmation message
        $whatsappText = "Hello,\n\n"
                      . "*FEE RECEIPT CONFIRMATION*\n"
                      . "-------------------------------------\n"
                      . "School: " . $schoolName . "\n"
                      . "Student Name: " . $feeCollection->student->name . "\n"
                      . "Class: " . ($feeCollection->student->schoolClass->name ?? 'N/A') . "\n"
                      . "Receipt No: " . $feeCollection->receipt_no . "\n"
                      . "Date: " . $feeCollection->payment_date->format('d-m-Y') . "\n"
                      . "Payment Mode: " . ucfirst($feeCollection->payment_mode) . "\n"
                      . "-------------------------------------\n";
                      
        foreach($feeCollection->feeCollectionItems as $item) {
            $whatsappText .= "- " . $item->feeType->name . ": ₹" . number_format($item->amount, 2) . "\n";
        }
        
        $whatsappText .= "-------------------------------------\n"
                       . "*Total Paid: ₹" . number_format($feeCollection->final_amount, 2) . "*\n\n"
                       . "Thank you for your payment.\n"
                       . "This is a computer-generated confirmation.";
    @endphp

    <div class="action-buttons" style="position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; align-items: center; z-index: 9999;">
        <div style="background: rgba(255,255,255,0.95); border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 10px; display: flex; gap: 5px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 11px; font-weight: bold; color: #475569; margin-right: 4px;">Print Layout:</span>
            <button id="btn-layout-a4" class="btn btn-primary" style="font-size: 11px; padding: 2px 8px; font-weight: bold;" onclick="setPrintLayout('a4')">A4</button>
            <button id="btn-layout-a5" class="btn" style="font-size: 11px; padding: 2px 8px; background-color: #cbd5e1; color: #1e293b; font-weight: bold;" onclick="setPrintLayout('a5')">A5</button>
        </div>
        <button class="btn btn-primary" onclick="window.print()">🖨 Print Receipt</button>
        <a href="{{ route('admin.fees.receipt.pdf', $feeCollection->id) }}" class="btn btn-success" target="_blank">📄 Download PDF</a>
        <a href="https://wa.me/91{{ $feeCollection->student->mobile ?? '9999999999' }}?text={{ urlencode($whatsappText) }}" class="btn btn-success" target="_blank">📲 Send WhatsApp</a>
        @if(!$feeCollection->trashed())
            @if(auth()->user() && (auth()->user()->hasRole('clerk') || auth()->user()->role === 'clerk'))
                <button class="btn btn-danger" onclick="reverseCollection()">🔄 Request Reversal</button>
            @else
                <button class="btn btn-danger" onclick="reverseCollection()">🔄 Reverse Collection</button>
            @endif
            <form id="reverseForm" method="POST" action="{{ route('admin.fees.reverse', $feeCollection->id) }}" style="display: none;">
                @csrf
                <input type="hidden" name="reason" id="reverseReason">
            </form>
            <script>
                function setPrintLayout(size) {
                    const container = document.querySelector('.receipt-container');
                    container.classList.remove('layout-a4', 'layout-a5');
                    container.classList.add('layout-' + size);
                    
                    document.getElementById('btn-layout-a4').className = size === 'a4' ? 'btn btn-primary' : 'btn';
                    document.getElementById('btn-layout-a4').style.backgroundColor = size === 'a4' ? '' : '#cbd5e1';
                    document.getElementById('btn-layout-a4').style.color = size === 'a4' ? '' : '#1e293b';

                    document.getElementById('btn-layout-a5').className = size === 'a5' ? 'btn btn-primary' : 'btn';
                    document.getElementById('btn-layout-a5').style.backgroundColor = size === 'a5' ? '' : '#cbd5e1';
                    document.getElementById('btn-layout-a5').style.color = size === 'a5' ? '' : '#1e293b';
                }

                function reverseCollection() {
                    const isClerk = @json(auth()->user() && (auth()->user()->hasRole('clerk') || auth()->user()->role === 'clerk'));
                    const actionWord = isClerk ? 'request reversal for' : 'reverse';
                    const reason = prompt('Please enter the reason for reversing this collection:');
                    if (reason && reason.trim() !== '') {
                        if (confirm('Are you sure you want to ' + actionWord + ' this fee collection?')) {
                            document.getElementById('reverseReason').value = reason;
                            document.getElementById('reverseForm').submit();
                        }
                    } else if (reason !== null) {
                        alert('A reason is required to reverse the collection.');
                    }
                }
            </script>
        @else
            <span style="background-color: #dc3545; color: white; padding: 10px 20px; border-radius: 5px; font-weight: 600; font-size: 14px;">CANCELLED / REVERSED</span>
        @endif
    </div>
    
    <div class="receipt-container layout-a4">
        <div class="receipt-header">
            <div class="header-content">
                <div class="logo-section">
                    @if($schoolLogo && file_exists(public_path('storage/' . $schoolLogo)))
                        <img src="{{ asset('storage/' . $schoolLogo) }}" alt="School Logo" style="max-width: 80px; max-height: 80px;">
                    @else
                        <div style="width: 80px; height: 80px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 12px; color: #666;">LOGO</span>
                        </div>
                    @endif
                </div>
                <div class="school-info">
                    <div class="school-name">{{ $schoolName }}</div>
                    <div class="school-address">{{ $schoolAddress }}</div>
                    <div class="school-address">Phone: {{ $schoolPhone }} | Email: {{ $schoolEmail }}</div>
                </div>
                <div class="receipt-info">
                    <div class="info-item">
                        <strong>Receipt No:</strong> {{ $feeCollection->receipt_no }}
                    </div>
                    <div class="info-item">
                        <strong>Date:</strong> {{ $feeCollection->payment_date->format('d-m-Y') }}
                    </div>
                    <div class="info-item">
                        <strong>Session:</strong> {{ date('m') >= 4 ? date('Y') . '-' . (date('Y')+1) : (date('Y')-1) . '-' . date('Y') }}
                    </div>
                </div>
            </div>
            <div class="receipt-title">FEE RECEIPT</div>
        </div>

        <div class="student-details" style="display: flex; gap: 15px; align-items: flex-start;">
            <img src="{{ $feeCollection->student->photo_url }}" alt="{{ $feeCollection->student->name }}"
                 style="width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; flex-shrink: 0;">
            <div class="details-grid" style="flex: 1;">
                <div class="detail-item">
                    <span class="detail-label">Student:</span>
                    <span class="detail-value">{{ $feeCollection->student->name }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Father:</span>
                    <span class="detail-value">{{ $feeCollection->student->father_name ?? $feeCollection->student->guardian_name ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Class:</span>
                    <span class="detail-value">{{ $feeCollection->student->schoolClass->name ?? $feeCollection->student->class_name ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Admission:</span>
                    <span class="detail-value">{{ $feeCollection->student->admission_no ?? $feeCollection->student->reg_no ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Mobile:</span>
                    <span class="detail-value">{{ $feeCollection->student->mobile ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Mode:</span>
                    <span class="detail-value">{{ ucfirst($feeCollection->payment_mode) }}</span>
                </div>
                @if(strtolower($feeCollection->payment_mode) !== 'cash' || $feeCollection->transaction_id)
                <div class="detail-item">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value">{{ $feeCollection->transaction_id ?? 'N/A' }}</span>
                </div>
                @endif
                <div class="detail-item">
                    <span class="detail-label">Receipt Status:</span>
                    <span class="detail-value">PAID</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Fee Month(s):</span>
                    <span class="detail-value" style="color: #15803d; font-weight: 800;">{{ is_array($feeCollection->fee_month) ? implode(', ', $feeCollection->fee_month) : ($feeCollection->fee_month ?? 'N/A') }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Collected By:</span>
                    <span class="detail-value">{{ $feeCollection->collectedBy->name ?? 'System' }}</span>
                </div>
            </div>
        </div>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>Fee Type</th>
                    <th>Description</th>
                    <th class="amount-cell">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($feeCollection->feeCollectionItems as $item)
                <tr>
                    <td>{{ $item->feeType->name }}</td>
                    <td>{{ $item->feeType->description ?? 'Fee Payment' }}</td>
                    <td class="amount-cell">₹{{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-section">
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
            
            <div class="summary-row">
                <div>Total Recurring Fee (Annualized):</div>
                <div>₹{{ number_format($recurringTotal, 2) }}</div>
            </div>
            <div class="summary-row">
                <div>Total One-time / Special Fees:</div>
                <div>₹{{ number_format($oneTimeTotal, 2) }}</div>
            </div>
            <div class="summary-row" style="font-weight: bold;">
                <div>Total Yearly Fee:</div>
                <div>₹{{ number_format($totalYearlyFee, 2) }}</div>
            </div>
            <div class="summary-row">
                <div>Paid Till Now:</div>
                <div>₹{{ number_format($totalPaid, 2) }}</div>
            </div>
            <div class="summary-row" style="color: #64748b; font-size: 11px; padding-left: 10px;">
                <div>• Remaining Recurring:</div>
                <div>₹{{ number_format($remainingRecurring, 2) }}</div>
            </div>
            <div class="summary-row" style="color: #64748b; font-size: 11px; padding-left: 10px;">
                <div>• Remaining One-time:</div>
                <div>₹{{ number_format($remainingOneTime, 2) }}</div>
            </div>
            <div class="summary-row" style="color: #dc3545; font-weight: bold;">
                <div>Total Remaining Fee:</div>
                <div>₹{{ number_format(max(0, $pendingFee), 2) }}</div>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row">
                <div>Current Payment:</div>
                <div>₹{{ number_format($feeCollection->total_amount, 2) }}</div>
            </div>
            @if($feeCollection->discount > 0)
            <div class="summary-row">
                <div>Discount:</div>
                <div>-₹{{ number_format($feeCollection->discount, 2) }}</div>
            </div>
            @endif
            @if($feeCollection->late_fine > 0)
            <div class="summary-row">
                <div>Late Fine:</div>
                <div>+₹{{ number_format($feeCollection->late_fine, 2) }}</div>
            </div>
            @endif
            <div class="summary-row summary-total">
                <div>Final Amount Paid:</div>
                <div>₹{{ number_format($feeCollection->final_amount, 2) }}</div>
            </div>
        </div>

        <div class="payment-details">
            <div class="info-row">
                <div class="info-label">Collected By:</div>
                <div class="info-value">{{ $feeCollection->collectedBy->name ?? 'System' }}</div>
            </div>
            @if(strtolower($feeCollection->payment_mode) !== 'cash' || $feeCollection->transaction_id)
            <div class="info-row">
                <div class="info-label">Transaction ID:</div>
                <div class="info-value">{{ $feeCollection->transaction_id ?? 'N/A' }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Remarks:</div>
                <div class="info-value">{{ $feeCollection->remarks ?? 'None' }}</div>
            </div>
        </div>

        <div class="qr-code">
            @php
                $paymentQr = \App\Models\AdminConfiguration::get('general', 'payment_qr');
            @endphp
            @if($paymentQr && file_exists(public_path('storage/' . $paymentQr)))
                <img src="{{ asset('storage/' . $paymentQr) }}" alt="School Payment QR Code" style="width: 100px; height: 100px;">
                <div>Scan QR for Payment</div>
            @else
                <!-- QR Code would be generated here in a real system -->
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23ffffff'/%3E%3Crect x='10' y='10' width='80' height='80' fill='%23f8f9fa'/%3E%3Ctext x='50' y='55' font-size='12' text-anchor='middle' fill='%236c757d'%3EPAYMENT%3C/text%3E%3C/svg%3E" alt="QR Code">
                <div>Setup Payment QR in Settings</div>
            @endif
        </div>

        <div class="footer-section">
            <div>This is a computer generated fee receipt</div>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div>Student/Guardian</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>Accountant</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>Principal</div>
                <div class="signature-line"></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print_immediately') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>