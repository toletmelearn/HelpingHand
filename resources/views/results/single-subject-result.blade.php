<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Single Subject Result Card</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .result-card {
            width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #2c3e50;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #e67e22;
            padding-bottom: 15px;
        }
        
        .logo-placeholder {
            width: 100px;
            height: 100px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
        }
        
        .school-info {
            text-align: center;
            flex-grow: 1;
        }
        
        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .affiliation {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }
        
        .contact-info {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .photo-placeholder {
            width: 100px;
            height: 120px;
            background: #3498db;
            border: 2px solid #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            text-align: center;
        }
        
        .student-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 15px;
            background: #ecf0f1;
            border-radius: 5px;
        }
        
        .info-block {
            flex: 1;
        }
        
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #34495e;
        }
        
        .result-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin: 25px 0;
            padding: 10px;
            background: #3498db;
            color: white;
            border-radius: 5px;
        }
        
        .subject-name {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 20px;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .result-table td {
            padding: 12px 15px;
            border: 1px solid #bdc3c7;
        }
        
        .label-cell {
            width: 40%;
            font-weight: bold;
            background: #ecf0f1;
            color: #2c3e50;
        }
        
        .value-cell {
            width: 60%;
            font-weight: bold;
            text-align: center;
        }
        
        .marks-obtained {
            background: #fff;
        }
        
        .total-marks {
            background: #fff;
        }
        
        .percentage {
            background: #fef9e7;
            color: #e67e22;
            font-size: 18px;
        }
        
        .grade {
            background: #fef9e7;
            color: #e67e22;
            font-size: 18px;
        }
        
        .status {
            background: #e8f5e8;
            color: #27ae60;
            font-size: 16px;
        }
        
        .comments {
            background: #fff;
        }
        
        .grading-scale {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        
        .scale-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 40px;
            text-align: center;
        }
        
        .signature-line {
            width: 150px;
            border-top: 1px solid #2c3e50;
            padding-top: 5px;
            margin-top: 40px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #bdc3c7;
            color: #7f8c8d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="result-card">
        <!-- Header Section -->
        <div class="header">
            <div class="logo-placeholder">
                SCHOOL<br>LOGO
            </div>
            
            <div class="school-info">
                <div class="school-name">UDAY NARAYAN SIKSHAN SANSTHAN</div>
                <div class="affiliation">Affiliation No: 1630012 | CBSE Board</div>
                <div class="contact-info">
                    Phone: +91 98765 43210 | Email: info@school.edu.in<br>
                    Website: www.school.edu.in | Address: Sector-12, Chandigarh
                </div>
            </div>
            
            <div class="photo-placeholder" style="background: none; padding: 0;">
                <img src="{{ $student_photo ?? asset('images/default-avatar.png') }}" alt="Student Photo" style="width:100px;height:120px;object-fit:cover;border:2px solid #2c3e50;">
            </div>
        </div>
        
        <!-- Student Information -->
        <div class="student-info">
            <div class="info-block">
                <div class="info-label">Name of Student</div>
                <div class="info-value">Pari Jain</div>
            </div>
            <div class="info-block">
                <div class="info-label">Roll No.</div>
                <div class="info-value">30</div>
            </div>
            <div class="info-block">
                <div class="info-label">Class</div>
                <div class="info-value">10th</div>
            </div>
            <div class="info-block">
                <div class="info-label">Date of Birth</div>
                <div class="info-value">15/08/2008</div>
            </div>
        </div>
        
        <!-- Result Title -->
        <div class="result-title">Academic Result – Term II</div>
        
        <!-- Subject Name -->
        <div class="subject-name">Subject: ENGLISH</div>
        
        <!-- Result Details Table -->
        <table class="result-table">
            <tr>
                <td class="label-cell">Marks Obtained</td>
                <td class="value-cell marks-obtained">30</td>
            </tr>
            <tr>
                <td class="label-cell">Total Marks</td>
                <td class="value-cell total-marks">60</td>
            </tr>
            <tr>
                <td class="label-cell">Percentage</td>
                <td class="value-cell percentage">50%</td>
            </tr>
            <tr>
                <td class="label-cell">Grade</td>
                <td class="value-cell grade">C</td>
            </tr>
            <tr>
                <td class="label-cell">Status</td>
                <td class="value-cell status">PASS</td>
            </tr>
            <tr>
                <td class="label-cell">Comments</td>
                <td class="value-cell comments">Pari Jain</td>
            </tr>
        </table>
        
        <!-- Grading Scale -->
        <div class="grading-scale">
            <div class="scale-title">Grading Scale:</div>
            A1 (91-100%) | A2 (81-90%) | B1 (71-80%) | B2 (61-70%) | C1 (51-60%) | C2 (41-50%) | D (33-40%) | E (Below 33%)
        </div>
        
        <!-- Signatures -->
        <div class="signatures">
            <div>
                <div class="signature-line">Class Teacher</div>
            </div>
            <div>
                <div class="signature-line">Principal</div>
            </div>
            <div>
                <div class="signature-line">Manager</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            This is a computer generated result card | Powered by HelpingHand ERP
        </div>
    </div>
</body>
</html>