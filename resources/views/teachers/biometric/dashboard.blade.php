@extends('layouts.admin')

@section('title', 'My Attendance & Performance')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-person-circle"></i> My Attendance & Performance
            </h1>
        </div>
    </div>

    <!-- Teacher Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Welcome Back</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $teacher->name }}</div>
                            <div class="text-xs text-gray-500">
                                Teacher ID: {{ $teacher->id }} | Email: {{ $teacher->email }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-badge fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Statistics -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Days</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_days'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Present Days</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['present_days'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Late Arrivals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['late_arrivals'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Early Departs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['early_departures'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-door-open fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Avg Hours</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['avg_working_hours'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Attendance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['attendance_percentage'] }}%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-primary" onclick="openAttendanceReport()">
                        <i class="bi bi-file-earmark-pdf"></i> Download Report
                    </button>
                    <button class="btn btn-success" onclick="viewPerformanceTrends()">
                        <i class="bi bi-graph-up"></i> Performance Trends
                    </button>
                    <a href="{{ route('teacher.biometric.notification-preferences') }}" class="btn btn-info">
                        <i class="bi bi-gear"></i> Notification Preferences
                    </a>
                </div>
                
                <div>
                    <select class="form-select" id="monthFilter" onchange="changeMonth()">
                        @php
                            $months = [];
                            for($i = 0; $i < 12; $i++) {
                                $month = now()->subMonths($i);
                                $isSelected = $month->format('Y-m') == now()->format('Y-m');
                                $months[] = [
                                    'value' => $month->format('Y-m'),
                                    'text' => $month->format('F Y'),
                                    'selected' => $isSelected
                                ];
                            }
                        @endphp
                        @foreach($months as $month)
                            <option value="{{ $month['value'] }}" {{ $month['selected'] ? 'selected' : '' }}>
                                {{ $month['text'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Attendance</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="attendanceDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="attendanceDropdown">
                            <div class="dropdown-header">Attendance Options:</div>
                            <a class="dropdown-item" href="#">View All</a>
                            <a class="dropdown-item" href="#">Export to CSV</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Print Summary</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="recentAttendanceTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>In Time</th>
                                    <th>Out Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $record)
                                <tr>
                                    <td>{{ $record->date->format('M d, Y') }}</td>
                                    <td>{{ $record->date->format('l') }}</td>
                                    <td>{{ $record->first_in_time ? $record->first_in_time->format('h:i A') : 'N/A' }}</td>
                                    <td>{{ $record->last_out_time ? $record->last_out_time->format('h:i A') : 'N/A' }}</td>
                                    <td>{{ $record->working_duration_formatted }}</td>
                                    <td>
                                        <span class="badge {{ $record->status_badge['class'] }}">
                                            {{ $record->status_badge['text'] }}
                                            @if($record->late_minutes > 0)
                                                Late {{ $record->late_minutes }}m
                                            @elseif($record->early_departure_minutes > 0)
                                                Early {{ $record->early_departure_minutes }}m
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ $record->remarks ?: 'Regular day' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No attendance records found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h3 class="display-6">{{ $stats['punctuality_score'] }}%</h3>
                                    <p class="card-text">Punctuality</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3 class="display-6">{{ $stats['discipline_score'] }}%</h3>
                                    <p class="card-text">Discipline</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h3 class="display-6">{{ $stats['attendance_percentage'] }}%</h3>
                                    <p class="card-text">Consistency</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    @php
                                        $grade = '';
                                        if($stats['discipline_score'] >= 90) $grade = 'A+';
                                        elseif($stats['discipline_score'] >= 80) $grade = 'A';
                                        elseif($stats['discipline_score'] >= 70) $grade = 'B+';
                                        elseif($stats['discipline_score'] >= 60) $grade = 'B';
                                        elseif($stats['discipline_score'] >= 50) $grade = 'C';
                                        else $grade = 'D';
                                    @endphp
                                    <h3 class="display-6">{{ $grade }}</h3>
                                    <p class="card-text">Grade</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="col-md-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-primary">{{ $stats['total_days'] }}</h4>
                                <p class="text-muted">Total Days</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-success">{{ $stats['present_days'] }}</h4>
                                <p class="text-muted">Present</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-warning">{{ $stats['late_arrivals'] }}</h4>
                                <p class="text-muted">Late Arrivals</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-danger">{{ $stats['early_departures'] }}</h4>
                                <p class="text-muted">Early Departs</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-center">
                                <h4 class="text-info">{{ $stats['avg_working_hours'] }} hrs</h4>
                                <p class="text-muted">Avg. Daily Hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @forelse($notifications as $notification)
                        <a href="{{ $notification['link'] }}" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $notification['title'] }}</h6>
                                <small class="text-muted">{{ $notification['timestamp'] }}</small>
                            </div>
                            <p class="mb-1">{{ $notification['message'] }}</p>
                            <small class="text-muted">{{ $notification['type'] }}</small>
                        </a>
                        @empty
                        <div class="text-center p-4">
                            <i class="bi bi-bell-slash fs-1 text-muted"></i>
                            <p class="text-muted">No recent notifications</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Download Report Modal -->
<div class="modal fade" id="downloadReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Download Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="reportType" class="form-label">Report Type</label>
                    <select class="form-select" id="reportType">
                        <option value="attendance">Attendance Summary</option>
                        <option value="performance">Performance Report</option>
                        <option value="detailed">Detailed Attendance</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="reportFormat" class="form-label">Format</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="format" id="pdfFormat" value="pdf" checked>
                        <label class="form-check-label" for="pdfFormat">
                            PDF Document
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="format" id="excelFormat" value="excel">
                        <label class="form-check-label" for="excelFormat">
                            Excel Spreadsheet
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="downloadReport()">Download</button>
            </div>
        </div>
    </div>
</div>

<script>
function openAttendanceReport() {
    var modal = new bootstrap.Modal(document.getElementById('downloadReportModal'));
    modal.show();
}

function viewPerformanceTrends() {
    // Fetch performance trends data via AJAX
    fetch('{{ route("teacher.biometric.monthly-summary") }}')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Performance trends would show detailed analysis based on: ' + 
                      'Total Days: ' + data.summary.total_days + ', ' +
                      'Attendance: ' + data.summary.attendance_percentage + '%, ' +
                      'Late Arrivals: ' + data.summary.late_arrivals);
            }
        })
        .catch(error => {
            console.error('Error fetching performance trends:', error);
            alert('Could not load performance trends');
        });
}

function changeMonth() {
    // Change month logic
    const month = document.getElementById('monthFilter').value;
    console.log('Month changed to: ' + month);
    
    // You could reload the page or fetch new data via AJAX
    // window.location.href = '{{ url()->current() }}?month=' + month;
}

function downloadReport() {
    // Get selected options
    const reportType = document.getElementById('reportType').value;
    const format = document.querySelector('input[name="format"]:checked').value;
    
    // Show loading state
    const btn = document.querySelector('#downloadReportModal .btn-primary');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparing...';
    btn.disabled = true;
    
    // Prepare form data for download
    const formData = new FormData();
    formData.append('type', reportType);
    formData.append('format', format);
    formData.append('month', document.getElementById('monthFilter').value);
    formData.append('_token', '{{ csrf_token() }}');
    
    // Submit via POST to download endpoint
    fetch('{{ route("teacher.biometric.dashboard") }}/download', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Download failed');
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `${reportType}_report_${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        // Hide modal and reset button
        var modal = bootstrap.Modal.getInstance(document.getElementById('downloadReportModal'));
        modal.hide();
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error downloading report: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
@endsection
