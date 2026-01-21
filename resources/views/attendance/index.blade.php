<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelpingHand - Attendance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📋 Attendance Management</h1>
            <div>
                <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Mark Attendance
                </a>
                <a href="{{ route('attendance.reports') }}" class="btn btn-info">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
                <a href="{{ route('attendance.export') }}" class="btn btn-success">
                    <i class="bi bi-download"></i> Export
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Attendance Records</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" value="{{ request('date') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="class" class="form-label">Class</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">All Classes</option>
                            @foreach($classes as $cls)
                                <option value="{{ $cls }}" {{ request('class') == $cls ? 'selected' : '' }}>
                                    {{ $cls }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                            <option value="half_day" {{ request('status') == 'half_day' ? 'selected' : '' }}>Half Day</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Records Table -->
        @if($attendances->count() > 0)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Attendance Records</h5>
                    <span class="badge bg-primary">Total: {{ $attendances->total() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Subject</th>
                                <th>Period</th>
                                <th>Marks By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendances as $attendance)
                            <tr>
                                <td>{{ $attendance->date->format('d/m/Y') }}</td>
                                <td><span class="badge bg-info">{{ $attendance->class }}</span></td>
                                <td>
                                    @if($attendance->student)
                                        <strong>{{ $attendance->student->name }}</strong>
                                        <br><small class="text-muted">Roll: {{ $attendance->student->roll_number }}</small>
                                    @else
                                        <span class="text-muted">Student N/A</span>
                                    @endif
                                </td>
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
                                <td>{{ $attendance->period ?? 'N/A' }}</td>
                                <td>
                                    @if($attendance->markedBy)
                                        {{ $attendance->markedBy->name }}
                                    @else
                                        System
                                    @endif
                                    <br><small class="text-muted">{{ $attendance->created_at->format('H:i') }}</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('attendance.show', $attendance) }}" 
                                           class="btn btn-outline-info" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form action="{{ route('attendance.destroy', $attendance) }}" 
                                              method="POST" 
                                              style="display: inline;"
                                              onsubmit="return confirm('Delete this attendance record?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $attendances->links() }}
                </div>
            </div>
        @else
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> 
                No attendance records found. 
                <a href="{{ route('attendance.create') }}" class="alert-link">Mark attendance now!</a>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>