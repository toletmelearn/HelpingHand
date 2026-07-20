<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CBSE Report Card</title>
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
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 7px;
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
        
        h2, h3 {
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
        
        .grand-total {
            background: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td style="width:90px;">
                    <img src="{{ public_path('images/school-logo.png') }}" style="width:80px;height:80px;">
                </td>
                <td style="text-align:center;">
                    <h2>{{ $school_name }}</h2>
                    <div>{{ $school_address }}</div>
                    <div>Affiliation No: {{ $affiliation_no }}</div>
                    <h3>ACADEMIC REPORT CARD</h3>
                    <div>Session: {{ $academic_year }}</div>
                </td>
                <td style="width:90px;text-align:right;">
                    <img src="{{ $student_photo ? public_path($student_photo) : public_path('images/default-avatar.png') }}" style="width:80px;height:90px;border:1px solid #000;">
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

        <!-- SCHOLASTIC TABLE -->
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Periodic Test</th>
                    <th>Notebook</th>
                    <th>SEA</th>
                    <th>Half Yearly / Annual</th>
                    <th>Total</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                {!! $marks_rows !!}
            </tbody>
            <tfoot>
                <tr class="grand-total">
                    <td colspan="5">Grand Total</td>
                    <td>{{ $grand_total }}</td>
                    <td>{{ $overall_grade }}</td>
                </tr>
                <tr>
                    <td colspan="7"><b>Percentage:</b> {{ $percentage }}%</td>
                </tr>
                <tr>
                    <td colspan="7"><b>Result:</b> {{ $final_result }}</td>
                </tr>
            </tfoot>
        </table>

        <br>

        <!-- CO-SCHOLASTIC -->
        <table>
            <tr>
                <th>Co-Scholastic Area</th>
                <th>Grade</th>
            </tr>
            {!! $coscholastic_rows !!}
        </table>

        <br>

        <!-- ATTENDANCE -->
        <table class="student-details">
            <tr>
                <td><b>Attendance:</b> {{ $attendance }}</td>
                <td><b>Working Days:</b> {{ $working_days }}</td>
            </tr>
        </table>

        <br>

        <!-- REMARK -->
        <table class="student-details">
            <tr>
                <td><b>Class Teacher Remark:</b> {{ $remarks }}</td>
            </tr>
        </table>

        <br><br>

        <!-- SIGN -->
        <table class="footer-table">
            <tr>
                <td>_____________________<br>Class Teacher</td>
                <td>_____________________<br>Principal</td>
            </tr>
        </table>

        <p class="powered-by">
            This is computer generated report card | Powered by HelpingHand ERP
        </p>
    </div>
</body>
</html>