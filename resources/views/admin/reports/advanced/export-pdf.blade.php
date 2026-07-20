<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Advanced Reporting Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-info {
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            background: #007bff;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Advanced Reporting Dashboard</h1>
    
    <div class="header-info">
        <strong>Report Generated:</strong> {{ now()->format('F d, Y H:i:s') }}<br>
        <strong>Date Range:</strong> {{ ucfirst(str_replace('_', ' ', $dateRange ?? 'This Month')) }}<br>
        @if($academicSessionId)
            <strong>Academic Session:</strong> {{ \App\Models\AcademicSession::find($academicSessionId)->name ?? 'N/A' }}<br>
        @endif
        @if($classId)
            <strong>Class:</strong> {{ \App\Models\SchoolClass::find($classId)->name ?? 'N/A' }}<br>
        @endif
    </div>

    <!-- Student Statistics -->
    <div class="section">
        <div class="section-title">STUDENT STATISTICS</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Students</div>
                <div class="stat-value">{{ $studentStats['total_students'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">New Admissions</div>
                <div class="stat-value">{{ $studentStats['new_admissions'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Students</div>
                <div class="stat-value">{{ $studentStats['active_students'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Passed Out / Left School</div>
                <div class="stat-value">{{ $studentStats['passed_out'] }} / {{ $studentStats['left_school'] }}</div>
            </div>
        </div>
    </div>

    <!-- Fee Statistics -->
    <div class="section">
        <div class="section-title">FEE STATISTICS</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Fees Collected</div>
                <div class="stat-value">₹{{ number_format($feeStats['total_fees_collected']) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Dues</div>
                <div class="stat-value">₹{{ number_format($feeStats['pending_dues']) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Overdue Fees</div>
                <div class="stat-value">₹{{ number_format($feeStats['overdue_fees']) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Payments This Period</div>
                <div class="stat-value">{{ $feeStats['payments_this_period'] }}</div>
            </div>
        </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="section">
        <div class="section-title">ATTENDANCE STATISTICS</div>
        <table class="table">
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Attendance Rate</td>
                <td><strong>{{ $attendanceStats['attendance_rate'] }}%</strong></td>
            </tr>
            <tr>
                <td>Total Attendance Records</td>
                <td>{{ $attendanceStats['total_attendance'] }}</td>
            </tr>
            <tr>
                <td>Present Count</td>
                <td>{{ $attendanceStats['present_count'] }}</td>
            </tr>
            <tr>
                <td>Absent Count</td>
                <td>{{ $attendanceStats['absent_count'] }}</td>
            </tr>
            <tr>
                <td>Late Arrivals</td>
                <td>{{ $attendanceStats['late_arrivals'] }}</td>
            </tr>
        </table>
    </div>

    <!-- Exam Statistics -->
    <div class="section">
        <div class="section-title">EXAM STATISTICS</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Exams</div>
                <div class="stat-value">{{ $examStats['total_exams'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Upcoming Exams</div>
                <div class="stat-value">{{ $examStats['upcoming_exams'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed Exams</div>
                <div class="stat-value">{{ $examStats['completed_exams'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Results Published</div>
                <div class="stat-value">{{ $examStats['results_published'] }}</div>
            </div>
        </div>
    </div>

    <!-- Library and Biometric Statistics -->
    <div class="section">
        <div class="section-title">LIBRARY & BIOMETRIC STATISTICS</div>
        <table class="table">
            <tr>
                <th>Category</th>
                <th>Metric</th>
                <th>Value</th>
            </tr>
            <tr>
                <td rowspan="5"><strong>Library</strong></td>
                <td>Total Books</td>
                <td>{{ $libraryStats['total_books'] }}</td>
            </tr>
            <tr>
                <td>Available Books</td>
                <td>{{ $libraryStats['available_books'] }}</td>
            </tr>
            <tr>
                <td>Issued Books</td>
                <td>{{ $libraryStats['issued_books'] }}</td>
            </tr>
            <tr>
                <td>Books Issued This Period</td>
                <td>{{ $libraryStats['books_issued_this_period'] }}</td>
            </tr>
            <tr>
                <td>Overdue Books</td>
                <td>{{ $libraryStats['overdue_books'] }}</td>
            </tr>
            <tr>
                <td rowspan="5"><strong>Biometric</strong></td>
                <td>Teacher Attendance Rate</td>
                <td><strong>{{ $biometricStats['attendance_rate'] }}%</strong></td>
            </tr>
            <tr>
                <td>Total Records</td>
                <td>{{ $biometricStats['total_teacher_records'] }}</td>
            </tr>
            <tr>
                <td>On Time Arrivals</td>
                <td>{{ $biometricStats['on_time_arrivals'] }}</td>
            </tr>
            <tr>
                <td>Late Arrivals</td>
                <td>{{ $biometricStats['late_arrivals'] }}</td>
            </tr>
            <tr>
                <td>Early Departures</td>
                <td>{{ $biometricStats['early_departures'] }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>HelpingHand School Management System | Generated on {{ now()->format('F d, Y') }}</p>
        <p>This is a computer-generated report</p>
    </div>
</body>
</html>
