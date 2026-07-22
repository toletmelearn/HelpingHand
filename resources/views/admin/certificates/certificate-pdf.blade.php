<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - {{ $certificate->serial_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .certificate-container {
            background: white;
            padding: 40px;
            position: relative;
        }
        .certificate-header {
            text-align: center;
            margin-bottom: 20px;
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
            margin-bottom: 10px;
        }
        .certificate-meta {
            text-align: center;
            font-size: 14px;
            color: #2c3e50;
            border: 1px solid #ccc;
            padding: 8px;
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
            width: 100%;
            margin-top: 60px;
        }
        .signature-section td {
            text-align: center;
            width: 33%;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 150px;
            margin: 20px auto 0;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-header">
            <div class="certificate-title">SCHOOL CERTIFICATE</div>
            <div class="certificate-subtitle">{{ strtoupper($certificate->certificate_type) }} - {{ $certificate->serial_number }}</div>
        </div>

        @if($certificate->certificate_type === 'tc')
        <div class="certificate-meta">
            <strong>Serial No:</strong> {{ $certificate->serial_number }}
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Issue Date:</strong> {{ ($certificate->published_at ?? $certificate->created_at)->format('d/m/Y') }}
        </div>
        @endif

        <div class="certificate-content">
            {!! $content !!}
        </div>

        <div class="certificate-footer">
            <table class="signature-section">
                <tr>
                    <td>
                        <div class="signature-line"></div>
                        <div>Principal</div>
                    </td>
                    <td>
                        <div class="signature-line"></div>
                        <div>Date: {{ date('d/m/Y') }}</div>
                    </td>
                    <td>
                        <div class="signature-line"></div>
                        <div>Official Seal</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
