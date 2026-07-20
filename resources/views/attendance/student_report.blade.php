<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Report - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-bar-chart"></i> Student Attendance Report</h1>
            <div>
                <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Attendance
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Student Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> {{ $student->name }}</p>
                        <p><strong>Class:</strong> <span class="badge bg-info">{{ $student->class }}</span></p>
                        <p><strong>Roll Number:</strong> {{ $student->roll_number }}</p>
                        <p><strong>Session:</strong> {{ $student->session ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar"></i> Monthly Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded">
                                    <h3 class="text-success">{{ $report['summary']['present'] }}</h3>
                                    <p class="mb-0">Present</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded">
                                    <h3 class="text-danger">{{ $report['summary']['absent'] }}</h3>
                                    <p class="mb-0">Absent</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded">
                                    <h3 class="text-warning">{{ $report['summary']['late'] }}</h3>
                                    <p class="mb-0">Late</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-light p-3 rounded">
                                    <h3 class="text-primary">{{ $report['summary']['percentage'] }}%</h3>
                                    <p class="mb-0">Attendance Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daily Attendance Details</h5>
            </div>
            <div class="card-body">
                @if(!empty($report['details']))
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['details'] as $detail)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($detail['date'])->format('d M Y') }}</td>
                                        <td>
                                            @if($detail['status'] == 'present')
                                                <span class="badge bg-success">Present</span>
                                            @elseif($detail['status'] == 'absent')
                                                <span class="badge bg-danger">Absent</span>
                                            @elseif($detail['status'] == 'late')
                                                <span class="badge bg-warning">Late</span>
                                            @else
                                                <span class="badge bg-secondary">Half Day</span>
                                            @endif
                                        </td>
                                        <td>{{ $detail['remarks'] ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-info-circle" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="mt-3">No Attendance Records Found</h4>
                        <p class="text-muted">No attendance records available for this student in the selected period.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>