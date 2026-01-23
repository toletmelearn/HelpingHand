<!DOCTYPE html>
<html>
<head>
    <title>Category Distribution Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .info-table th {
            background-color: #f2f2f2;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            background-color: #FF9800;
            color: white;
            padding: 10px;
            margin-top: 0;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .summary-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Category Distribution Report</h1>
        <p>Generated on: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="section">
        <h3>Summary</h3>
        <table class="summary-table">
            <tr>
                <th>Category</th>
                <th>Total Students</th>
                <th>Male</th>
                <th>Female</th>
                <th>Other</th>
                <th>Percentage</th>
            </tr>
            @php
                $totalStudents = 0;
                foreach($categoryWise as $category => $students) {
                    $totalStudents += $students->count();
                }
            @endphp
            
            @foreach($categoryWise as $category => $students)
            <tr>
                <td><strong>{{ $category }}</strong></td>
                <td>{{ $students->count() }}</td>
                <td>{{ $students->where('gender', 'male')->count() }}</td>
                <td>{{ $students->where('gender', 'female')->count() }}</td>
                <td>{{ $students->where('gender', 'other')->count() }}</td>
                <td>{{ $totalStudents > 0 ? round(($students->count() / $totalStudents) * 100, 2) : 0 }}%</td>
            </tr>
            @endforeach
            <tr>
                <td><strong>TOTAL</strong></td>
                <td><strong>{{ $totalStudents }}</strong></td>
                <td><strong>{{ collect($categoryWise)->flatMap(fn($cat) => $cat)->where('gender', 'male')->count() }}</strong></td>
                <td><strong>{{ collect($categoryWise)->flatMap(fn($cat) => $cat)->where('gender', 'female')->count() }}</strong></td>
                <td><strong>{{ collect($categoryWise)->flatMap(fn($cat) => $cat)->where('gender', 'other')->count() }}</strong></td>
                <td><strong>100%</strong></td>
            </tr>
        </table>
    </div>

    @foreach($categoryWise as $category => $students)
    @if($students->count() > 0)
    <div class="section">
        <h3>Category: {{ $category }} ({{ $students->count() }} Students)</h3>
        <table class="info-table">
            <tr>
                <th>Roll Number</th>
                <th>Student Name</th>
                <th>Class</th>
                <th>Gender</th>
                <th>Section</th>
            </tr>
            @foreach($students as $student)
            <tr>
                <td>{{ $student->roll_number }}</td>
                <td>{{ $student->name }}</td>
                <td>{{ $student->class }}</td>
                <td>{{ ucfirst($student->gender) }}</td>
                <td>{{ $student->section }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif
    @endforeach
</body>
</html>