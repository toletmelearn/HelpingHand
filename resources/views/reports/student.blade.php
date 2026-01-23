<!DOCTYPE html>
<html>
<head>
    <title>Student Report - {{ $student->name }}</title>
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
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Report</h1>
        <h2>{{ $student->name }}</h2>
    </div>

    <div class="section">
        <h3>Basic Information</h3>
        <table class="info-table">
            <tr>
                <th>Field</th>
                <th>Details</th>
            </tr>
            <tr>
                <td>Name</td>
                <td>{{ $student->name }}</td>
            </tr>
            <tr>
                <td>Father's Name</td>
                <td>{{ $student->father_name }}</td>
            </tr>
            <tr>
                <td>Mother's Name</td>
                <td>{{ $student->mother_name }}</td>
            </tr>
            <tr>
                <td>Date of Birth</td>
                <td>{{ $student->date_of_birth ? $student->date_of_birth->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Gender</td>
                <td>{{ ucfirst($student->gender) }}</td>
            </tr>
            <tr>
                <td>Category</td>
                <td>{{ $student->category }}</td>
            </tr>
            <tr>
                <td>Class</td>
                <td>{{ $student->class }}</td>
            </tr>
            <tr>
                <td>Section</td>
                <td>{{ $student->section }}</td>
            </tr>
            <tr>
                <td>Roll Number</td>
                <td>{{ $student->roll_number }}</td>
            </tr>
            <tr>
                <td>Religion</td>
                <td>{{ $student->religion }}</td>
            </tr>
            <tr>
                <td>Caste</td>
                <td>{{ $student->caste }}</td>
            </tr>
            <tr>
                <td>Blood Group</td>
                <td>{{ $student->blood_group }}</td>
            </tr>
            <tr>
                <td>Phone</td>
                <td>{{ $student->phone }}</td>
            </tr>
            <tr>
                <td>Aadhar Number</td>
                <td>{{ $student->aadhar_number }}</td>
            </tr>
            <tr>
                <td>Address</td>
                <td>{{ $student->address }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Academic Information</h3>
        <table class="info-table">
            <tr>
                <th>Subject</th>
                <th>Marks Obtained</th>
                <th>Total Marks</th>
                <th>Grade</th>
            </tr>
            @forelse($student->results as $result)
            <tr>
                <td>{{ $result->subject }}</td>
                <td>{{ $result->marks_obtained }}</td>
                <td>{{ $result->total_marks }}</td>
                <td>{{ $result->grade }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4">No academic results found</td>
            </tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <h3>Attendance Summary</h3>
        @if($student->attendances->count() > 0)
        <table class="info-table">
            <tr>
                <th>Total Days</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Late</th>
                <th>Attendance %</th>
            </tr>
            <tr>
                <td>{{ $student->attendances->count() }}</td>
                <td>{{ $student->attendances->where('status', 'present')->count() }}</td>
                <td>{{ $student->attendances->where('status', 'absent')->count() }}</td>
                <td>{{ $student->attendances->where('status', 'late')->count() }}</td>
                <td>{{ $student->attendances->count() > 0 ? round(($student->attendances->where('status', 'present')->count() / $student->attendances->count()) * 100, 2) : 0 }}%</td>
            </tr>
        </table>
        @else
        <p>No attendance records found</p>
        @endif
    </div>

    <div class="section">
        <h3>Fee Status</h3>
        <table class="info-table">
            <tr>
                <th>Fee Description</th>
                <th>Amount</th>
                <th>Paid Amount</th>
                <th>Due Amount</th>
                <th>Status</th>
                <th>Due Date</th>
            </tr>
            @forelse($student->fees as $fee)
            <tr>
                <td>{{ $fee->description }}</td>
                <td>₹{{ number_format($fee->amount, 2) }}</td>
                <td>₹{{ number_format($fee->paid_amount, 2) }}</td>
                <td>₹{{ number_format($fee->due_amount, 2) }}</td>
                <td>{{ ucfirst($fee->status) }}</td>
                <td>{{ $fee->due_date }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6">No fee records found</td>
            </tr>
            @endforelse
        </table>
    </div>
</body>
</html>