<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-bar-chart"></i> Attendance Reports</h1>
            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Attendance
            </a>
        </div>

        <!-- Report Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Generate Report</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.reports') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date *</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="{{ $date }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="class" class="form-label">Class</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">Select Class</option>
                            @foreach($classes as $cls)
                                <option value="{{ $cls }}" {{ $class == $cls ? 'selected' : '' }}>
                                    {{ $cls }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-graph-up"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        @if(isset($stats))
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h3>{{ $stats['total'] }}</h3>
                        <p class="mb-0">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h3>{{ $stats['present'] }}</h3>
                        <p class="mb-0">Present</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <h3>{{ $stats['absent'] }}</h3>
                        <p class="mb-0">Absent</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <h3>{{ $stats['late'] }}</h3>
                        <p class="mb-0">Late</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Percentage Card -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h2>Attendance Percentage: 
                            <span class="badge bg-{{ $stats['percentage'] >= 90 ? 'success' : ($stats['percentage'] >= 75 ? 'warning' : 'danger') }}">
                                {{ $stats['percentage'] }}%
                            </span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Attendance List -->
        @if($attendances->count() > 0)
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Detailed Attendance for {{ $date }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Subject</th>
                            <th>Period</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->student->roll_number ?? 'N/A' }}</td>
                            <td>{{ $attendance->student->name ?? 'N/A' }}</td>
                            <td>
                                @if($attendance->status == 'present')
                                    <span class="badge bg-success">Present</span>
                                @elseif($attendance->status == 'absent')
                                    <span class="badge bg-danger">Absent</span>
                                @elseif($attendance->status == 'late')
                                    <span class="badge bg-warning">Late</span>
                                @else
                                    <span class="badge bg-secondary">Half Day</span>
                                @endif
                            </td>
                            <td>{{ $attendance->subject ?? 'N/A' }}</td>
                            <td>{{ $attendance->period ?? 'Full Day' }}</td>
                            <td>{{ $attendance->remarks ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endif

        <!-- Instructions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> How to Use</h5>
            </div>
            <div class="card-body">
                <ul>
                    <li>Select a date to view attendance for that day</li>
                    <li>Optionally filter by class to see specific class attendance</li>
                    <li>View statistics and detailed attendance records</li>
                    <li>Export reports using the export button on the attendance page</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>