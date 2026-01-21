<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Details - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-eye"></i> Attendance Details</h1>
            <div>
                <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                <a href="{{ route('attendance.edit', $attendance) }}" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Attendance Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Date:</strong> {{ $attendance->date->format('d F Y') }}</p>
                                <p><strong>Class:</strong> <span class="badge bg-info">{{ $attendance->class }}</span></p>
                                <p><strong>Subject:</strong> {{ $attendance->subject ?? 'N/A' }}</p>
                                <p><strong>Period:</strong> {{ $attendance->period ?? 'Full Day' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <strong>Status:</strong> 
                                    @if($attendance->status == 'present')
                                        <span class="badge bg-success">Present</span>
                                    @elseif($attendance->status == 'absent')
                                        <span class="badge bg-danger">Absent</span>
                                    @elseif($attendance->status == 'late')
                                        <span class="badge bg-warning">Late</span>
                                    @else
                                        <span class="badge bg-secondary">Half Day</span>
                                    @endif
                                </p>
                                <p><strong>Session:</strong> {{ $attendance->session ?? 'N/A' }}</p>
                                <p><strong>Remarks:</strong> {{ $attendance->remarks ?? 'No remarks' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Student Information</h5>
                    </div>
                    <div class="card-body">
                        @if($attendance->student)
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> {{ $attendance->student->name }}</p>
                                    <p><strong>Father's Name:</strong> {{ $attendance->student->father_name }}</p>
                                    <p><strong>Mother's Name:</strong> {{ $attendance->student->mother_name }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Roll Number:</strong> {{ $attendance->student->roll_number }}</p>
                                    <p><strong>Aadhar:</strong> {{ substr($attendance->student->aadhar_number, 0, 4) }}****{{ substr($attendance->student->aadhar_number, -4) }}</p>
                                    <p><strong>Phone:</strong> {{ $attendance->student->phone }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-muted">Student information not available</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock"></i> Record Details</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Created:</strong> {{ $attendance->created_at->format('d/m/Y H:i:s') }}</p>
                        <p><strong>Last Updated:</strong> {{ $attendance->updated_at->format('d/m/Y H:i:s') }}</p>
                        
                        <hr>
                        
                        <p><strong>Marks By:</strong> 
                            @if($attendance->markedBy)
                                {{ $attendance->markedBy->name }}
                            @else
                                System/User
                            @endif
                        </p>
                        
                        <p><strong>IP Address:</strong> {{ $attendance->ip_address ?? 'N/A' }}</p>
                        
                        @if($attendance->device_info)
                            <p><strong>Device:</strong> {{ Str::limit($attendance->device_info, 50) }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-tools"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('attendance.edit', $attendance) }}" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Edit Record
                            </a>
                            
                            <form action="{{ route('attendance.destroy', $attendance) }}" 
                                  method="POST"
                                  onsubmit="return confirm('Are you sure you want to delete this attendance record?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-trash"></i> Delete Record
                                </button>
                            </form>
                            
                            <a href="{{ route('attendance.student.report', $attendance->student_id ?? 0) }}" 
                               class="btn btn-info">
                                <i class="bi bi-bar-chart"></i> Student Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>