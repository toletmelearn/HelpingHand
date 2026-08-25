<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result - {{ $result->subject }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px 12px; text-align: left; }
        th { width: 35%; background: #f2f2f2; }
        h2 { margin-bottom: 0; }
        .status-pass { color: #1a7f37; font-weight: bold; }
        .status-fail { color: #c0392b; font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <h2>{{ config('app.name', 'HelpingHand School ERP') }}</h2>
    <p>Result Slip</p>

    <table>
        <tr><th>Exam</th><td>{{ $result->exam->name ?? 'N/A' }}</td></tr>
        <tr><th>Subject</th><td>{{ $result->subject }}</td></tr>
        <tr><th>Marks Obtained</th><td>{{ $result->marks_obtained }}</td></tr>
        <tr><th>Total Marks</th><td>{{ $result->total_marks }}</td></tr>
        <tr><th>Percentage</th><td>{{ $result->percentage }}%</td></tr>
        <tr><th>Grade</th><td>{{ $result->grade }}</td></tr>
        <tr>
            <th>Status</th>
            <td class="status-{{ $result->result_status }}">{{ ucfirst($result->result_status) }}</td>
        </tr>
        <tr><th>Term</th><td>{{ $result->term }}</td></tr>
        <tr><th>Academic Year</th><td>{{ $result->academic_year }}</td></tr>
        @if($result->remarks)
        <tr><th>Remarks</th><td>{{ $result->remarks }}</td></tr>
        @endif
    </table>

    <p class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()">Print</button>
    </p>
</body>
</html>
