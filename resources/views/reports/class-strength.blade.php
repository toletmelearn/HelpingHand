<!DOCTYPE html>
<html>
<head>
    <title>Class Strength Report</title>
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
            background-color: #2196F3;
            color: white;
            padding: 10px;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Class Strength Report</h1>
        <p>Generated on: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    @foreach($students as $class => $classStudents)
    <div class="section">
        <h3>Class: {{ $class }} (Total: {{ $classStudents->count() }})</h3>
        <table class="info-table">
            <tr>
                <th>Roll Number</th>
                <th>Student Name</th>
                <th>Gender</th>
                <th>Section</th>
                <th>Category</th>
            </tr>
            @foreach($classStudents as $student)
            <tr>
                <td>{{ $student->roll_number }}</td>
                <td>{{ $student->name }}</td>
                <td>{{ ucfirst($student->gender) }}</td>
                <td>{{ $student->section }}</td>
                <td>{{ $student->category }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endforeach
</body>
</html>