<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card - {{ $idCard->student->name ?? 'Student' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .id-card-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border: 2px solid #333;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .id-card-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .school-logo {
            width: 80px;
            height: 80px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            overflow: hidden;
        }
        .school-logo img {
            max-width: 100%;
            max-height: 100%;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 5px 0;
        }
        .school-address {
            font-size: 12px;
            color: #666;
            margin: 3px 0;
        }
        .id-card-body {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        .student-photo {
            width: 100px;
            height: 120px;
            border: 1px solid #ccc;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
        }
        .student-photo img {
            max-width: 100%;
            max-height: 100%;
        }
        .student-info {
            flex: 1;
        }
        .info-row {
            display: flex;
            margin: 5px 0;
        }
        .info-label {
            width: 80px;
            font-weight: bold;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .id-card-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .card-number {
            font-size: 14px;
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
        }
        .qr-code {
            width: 60px;
            height: 60px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            display: inline-block;
            margin: 0 auto 10px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .signature-box {
            text-align: center;
            width: 120px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin: 10px 0 5px 0;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .id-card-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin: 5px 0;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print ID Card</button>
    
    <div class="id-card-container">
        <div class="id-card-header">
            <div class="school-logo">
                <!-- School Logo -->
            </div>
            <div class="school-name">HelpingHand Public School</div>
            <div class="school-address">123 Education Street, City Name, State - 123456</div>
            <div class="id-card-title">Student ID Card</div>
        </div>

        <div class="id-card-body">
            <div class="student-photo">
                <img src="{{ $idCard->student->photo_url ?? asset('images/default-avatar.png') }}" alt="Student Photo">
            </div>
            <div class="student-info">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value">{{ $idCard->student->name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Class:</div>
                    <div class="info-value">{{ $idCard->student->schoolClass->name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Roll No:</div>
                    <div class="info-value">{{ $idCard->student->roll_number ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Admission:</div>
                    <div class="info-value">{{ $idCard->student->admission_no ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Issue:</div>
                    <div class="info-value">{{ $idCard->issue_date->format('d-m-Y') ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Valid Till:</div>
                    <div class="info-value">{{ $idCard->expiry_date->format('d-m-Y') ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="id-card-footer">
            <div class="card-number">Card No: {{ $idCard->card_number ?? 'N/A' }}</div>
            <div class="qr-code">
                <!-- QR Code -->
            </div>
            <div>Scan QR for Verification</div>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div>Principal</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>Issued By</div>
                <div class="signature-line"></div>
            </div>
        </div>
    </div>
</body>
</html>