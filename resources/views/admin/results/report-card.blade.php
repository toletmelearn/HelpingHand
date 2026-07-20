<!DOCTYPE html>
<html>
<head>
    <title>Report Card - {{ $reportCardData['student_name'] }}</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Calibri, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .container-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .toolbar {
            background: #343a40;
            padding: 15px;
            text-align: center;
        }
        .toolbar a, .toolbar button {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 5px;
            border: none;
            cursor: pointer;
        }
        .toolbar a:hover, .toolbar button:hover {
            background: #0056b3;
        }
        .report-card {
            width: 900px;
            margin: 20px auto;
            border: 3px solid #000;
            padding: 25px;
            background: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .school-header {
            text-align: center;
        }
        .school-header h2 {
            margin: 0;
            font-size: 28px;
        }
        .school-header p {
            margin: 2px 0;
            font-size: 16px;
        }
        .school-header h3 {
            margin-top: 5px;
            font-size: 22px;
        }
        hr {
            border: 1px solid #000;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }
        table td {
            padding: 8px;
        }
        .marks-table {
            text-align: center;
            margin: 15px 0;
        }
        .marks-table th, .marks-table td {
            border: 1px solid #000;
            padding: 10px;
        }
        .marks-table thead {
            background: #eaeaea;
            font-weight: bold;
        }
        .summary-table {
            border: 2px solid #000;
            margin: 15px 0;
        }
        .summary-table td {
            padding: 10px;
        }
        .remarks {
            border: 2px solid #000;
            padding: 12px;
            font-size: 15px;
            margin: 15px 0;
        }
        .signatures {
            margin-top: 50px;
            font-size: 15px;
        }
        .signature-cell {
            text-align: center;
            vertical-align: bottom;
        }
        .footer-note {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
        }
        @media print {
            .report-card {
                margin: 0;
                border: none;
                box-shadow: none;
            }
            .toolbar {
                display: none;
            }
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .container-wrapper {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container-wrapper">
        <!-- Toolbar -->
        <div class="toolbar">
            <a href="{{ route('admin.results.index') }}">&larr; Back to Results</a>
            <button onclick="window.print()">🖨️ Print Report Card</button>
            <a href="{{ route('admin.results.report-card.print', [$reportCardData['student_id'], $reportCardData['exam_id']]) }}" target="_blank">📄 Printable Version</a>
            <a href="{{ route('admin.results.professional-format', [$reportCardData['student_id'], $reportCardData['exam_id']]) }}" target="_blank">📑 Professional Format</a>
            <a href="{{ route('admin.results.cbse-professional-format', [$reportCardData['student_id'], $reportCardData['exam_id']]) }}" target="_blank">📘 CBSE Professional Format</a>
        </div>

        <!-- Report Card Content -->
        <div class="report-card">
        <!-- SCHOOL HEADER -->
        <div class="school-header">
            <h2>{{ config('app.name', 'HelpingHand School') }}</h2>
            <p>Affiliated to CBSE</p>
            <h3>ACADEMIC REPORT CARD</h3>
            <p>Session: {{ date('Y') }}-{{ date('Y') + 1 }}</p>
        </div>

        <hr>

        <!-- STUDENT DETAILS -->
        <table>
            <tr>
                <td><strong>Student Name:</strong> {{ $reportCardData['student_name'] }}</td>
                <td><strong>Class:</strong> {{ $reportCardData['class_name'] }}</td>
                <td><strong>Section:</strong> {{ $reportCardData['section'] }}</td>
            </tr>
            <tr>
                <td><strong>Roll No:</strong> {{ $reportCardData['student_id'] }}</td>
                <td><strong>Exam:</strong> {{ App\Models\Exam::find($reportCardData['exam_id'])?->name ?? 'N/A' }}</td>
                <td><strong>Term:</strong> {{ App\Models\Exam::find($reportCardData['exam_id'])?->term ?? 'N/A' }}</td>
            </tr>
        </table>

        <br>
        <!-- MARKS TABLE -->
        <table class="marks-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Maximum Marks</th>
                    <th>Marks Obtained</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportCardData['subjects'] as $subjectResult)
                <tr>
                    <td>{{ $subjectResult['subject'] }}</td>
                    <td>{{ $subjectResult['total_marks'] }}</td>
                    <td>{{ $subjectResult['marks_obtained'] }}</td>
                    <td>{{ $subjectResult['percentage'] }}%</td>
                    <td>{{ $subjectResult['grade'] }}</td>
                    <td>{{ strtoupper($subjectResult['result_status']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <br>

        <!-- RESULT SUMMARY -->
        <table class="summary-table">
            <tr>
                <td><strong>Total Marks:</strong> {{ $reportCardData['total_marks'] }}</td>
                <td><strong>Obtained:</strong> {{ $reportCardData['total_obtained'] }}</td>
                <td><strong>Overall %:</strong> {{ $reportCardData['overall_percentage'] }}%</td>
            </tr>
            <tr>
                <td><strong>Overall Grade:</strong> {{ $reportCardData['overall_grade'] }}</td>
                <td><strong>Rank:</strong> {{ $reportCardData['class_rank'] ?? 'N/A' }}</td>
                <td><strong>Final Result:</strong> {{ $reportCardData['final_result'] }}</td>
            </tr>
        </table>

        <br>

        <!-- REMARKS -->
        <div class="remarks">
            <strong>Class Teacher Remarks:</strong><br>
            @if(!empty($reportCardData['subjects']) && isset($reportCardData['subjects'][0]['remarks']))
                {{ $reportCardData['subjects'][0]['remarks'] }}
            @else
                Outstanding performance. Keep up the excellent work!
            @endif
        </div>

        <br><br>

        <!-- SIGNATURE -->
        <table class="signatures" width="100%">
            <tr>
                <td class="signature-cell">
                    ___________________<br>
                    Class Teacher
                </td>
                <td class="signature-cell">
                    ___________________<br>
                    Exam Incharge
                </td>
                <td class="signature-cell">
                    ___________________<br>
                    Principal
                </td>
            </tr>
        </table>

        <p class="footer-note">
            *Computer generated report card – {{ config('app.name') }} ERP*
        </p>
    </div>

    </div> <!-- Close report-card div -->
    </div> <!-- Close container-wrapper div -->

    <script>
    // Auto-print functionality
    window.onload = function() {
        if (window.location.href.includes('print=true')) {
            window.print();
        }
    }
    </script>
</body>
</html>
