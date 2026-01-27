<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Preview - {{ $certificate->serial_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .certificate-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
        }
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .certificate-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .certificate-subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .certificate-content {
            line-height: 1.6;
            font-size: 16px;
            color: #34495e;
        }
        .certificate-footer {
            margin-top: 40px;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .signature-section {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }
        .signature {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 150px;
            margin: 20px auto;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Certificate</button>
    
    <div class="certificate-container">
        <div class="certificate-header">
            <div class="certificate-title">SCHOOL CERTIFICATE</div>
            <div class="certificate-subtitle">{{ strtoupper($certificate->certificate_type) }} - {{ $certificate->serial_number }}</div>
        </div>
        
        <div class="certificate-content">
            {!! $content !!}
        </div>
        
        <div class="certificate-footer">
            <div class="signature-section">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Principal</div>
                </div>
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Date: {{ date('d/m/Y') }}</div>
                </div>
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Official Seal</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>