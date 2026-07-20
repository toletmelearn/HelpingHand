<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Final Result Card</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        
        .container {
            width: 900px;
            margin: auto;
            border: 2px solid #000;
            padding: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 8px;
            text-align: center;
            border: 1px solid #000;
        }
        
        th {
            background: #e6e6e6;
            font-weight: bold;
        }
        
        .header-table td {
            border: none;
            text-align: left;
        }
        
        .student-details td {
            border: none;
            text-align: left;
            font-size: 14px;
        }
        
        .footer-table td {
            border: none;
            text-align: center;
        }
        
        h2, h3, h4 {
            margin: 0;
            text-align: center;
        }
        
        hr {
            border: 1px solid #000;
            margin: 15px 0;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .signature-line {
            margin-top: 40px;
        }
        
        .powered-by {
            text-align: center;
            font-size: 12px;
            margin-top: 20px;
        }
        
        .summary-row {
            background: #f2f2f2;
            font-weight: bold;
        }
        
        .final-result {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td style="width:90px;">
                    <img src="{{ $school_logo }}" style="width:80px;height:80px;">
                </td>
                <td style="text-align:center;">
                    <h2>{{ $school_name }}</h2>
                    <div>{{ $school_address }}</div>
                    <div>Affiliation No: {{ $affiliation_no }}</div>
                    <h3>ACADEMIC RESULT CARD</h3>
                    <div>Session: {{ $academic_year }}</div>
                </td>
                <td style="width:90px;text-align:right;">
                    <img src="{{ $student_photo }}" style="width:80px;height:90px;border:1px solid #000;">
                </td>
            </tr>
        </table>

        <hr>

        <!-- STUDENT DETAILS -->
        <table class="student-details">
            <tr>
                <td><b>Student Name:</b> {{ $student_name }}</td>
                <td><b>Class:</b> {{ $class }} - {{ $section }}</td>
            </tr>
            <tr>
                <td><b>Father's Name:</b> {{ $father_name }}</td>
                <td><b>Roll No:</b> {{ $roll_no }}</td>
            </tr>
            <tr>
                <td><b>Admission No:</b> {{ $admission_no }}</td>
                <td><b>DOB:</b> {{ $dob }}</td>
            </tr>
            <tr>
                <td><b>Exam:</b> {{ $exam_name }}</td>
                <td><b>Term:</b> {{ $term }}</td>
            </tr>
        </table>

        <br>

        <!-- RESULTS TABLE -->
        {!! $results_table !!}

        <!-- SUMMARY -->
        <table class="student-details">
            <tr>
                <td><b>Total Marks Obtained:</b> {{ $total_obtained }}</td>
                <td><b>Total Marks:</b> {{ $total_marks }}</td>
            </tr>
            <tr>
                <td><b>Overall Percentage:</b> {{ $overall_percentage }}%</td>
                <td><b>Overall Grade:</b> {{ $overall_grade }}</td>
            </tr>
        </table>

        <br>

        <!-- FINAL RESULT -->
        <div class="final-result">
            FINAL RESULT: 
            @php
                $resultColor = $final_result === 'PASS' ? '#27ae60' : '#e74c3c';
            @endphp
            <span style="color: {{ $resultColor }};">
                {{ $final_result }}
            </span>
        </div>

        <br>

        <!-- ATTENDANCE -->
        <table class="student-details">
            <tr>
                <td><b>Attendance:</b> {{ $attendance }} days</td>
                <td><b>Working Days:</b> {{ $working_days }} days</td>
            </tr>
        </table>

        <br>

        <!-- REMARKS -->
        <table class="student-details">
            <tr>
                <td><b>Class Teacher Remarks:</b> {{ $remarks }}</td>
            </tr>
        </table>

        <br><br>

        <!-- SIGNATURES -->
        <table class="footer-table">
            <tr>
                <td>_____________________________<br><b>Class Teacher</b></td>
                <td>_____________________________<br><b>Principal</b></td>
                <td>_____________________________<br><b>Parent/Guardian</b></td>
            </tr>
        </table>

        <!-- FOOTER -->
        <div class="powered-by">
            Generated by: {{ $generated_by }} | Date: {{ $generated_date }}<br>
            This is a computer generated result card | Powered by HelpingHand ERP
        </div>
    </div>
</body>
</html>