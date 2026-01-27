@extends('layouts.app')

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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ Auth::user()->name }}</div>
                            <div class="text-xs text-gray-500">
                                Teacher ID: {{ Auth::user()->id }} | Email: {{ Auth::user()->email }}
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">22</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">20</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">2</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">8.2</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">90.9%</div>
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
                </div>
                
                <div>
                    <select class="form-select" id="monthFilter" onchange="changeMonth()">
                        <option value="2026-01" selected>January 2026</option>
                        <option value="2025-12">December 2025</option>
                        <option value="2025-11">November 2025</option>
                        <option value="2025-10">October 2025</option>
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
                                <tr>
                                    <td>Jan 27, 2026</td>
                                    <td>Wednesday</td>
                                    <td>08:45 AM</td>
                                    <td>05:15 PM</td>
                                    <td>8.5 hrs</td>
                                    <td><span class="badge bg-success">On Time</span></td>
                                    <td>Regular day</td>
                                </tr>
                                <tr>
                                    <td>Jan 26, 2026</td>
                                    <td>Tuesday</td>
                                    <td>08:55 AM</td>
                                    <td>04:45 PM</td>
                                    <td>8.0 hrs</td>
                                    <td><span class="badge bg-warning">Late 5m</span></td>
                                    <td>Traffic delay</td>
                                </tr>
                                <tr>
                                    <td>Jan 25, 2026</td>
                                    <td>Monday</td>
                                    <td>08:30 AM</td>
                                    <td>05:30 PM</td>
                                    <td>9.0 hrs</td>
                                    <td><span class="badge bg-success">Early In</span></td>
                                    <td>Extra class</td>
                                </tr>
                                <tr>
                                    <td>Jan 24, 2026</td>
                                    <td>Sunday</td>
                                    <td>Off</td>
                                    <td>Off</td>
                                    <td>0 hrs</td>
                                    <td><span class="badge bg-secondary">Holiday</span></td>
                                    <td>Weekly off</td>
                                </tr>
                                <tr>
                                    <td>Jan 23, 2026</td>
                                    <td>Saturday</td>
                                    <td>09:00 AM</td>
                                    <td>04:30 PM</td>
                                    <td>7.5 hrs</td>
                                    <td><span class="badge bg-warning">Early Out</span></td>
                                    <td>Personal work</td>
                                </tr>
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
                                    <h3 class="display-6">94.2%</h3>
                                    <p class="card-text">Punctuality</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3 class="display-6">92.5%</h3>
                                    <p class="card-text">Discipline</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h3 class="display-6">90.8%</h3>
                                    <p class="card-text">Consistency</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h3 class="display-6">B+</h3>
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
                                <h4 class="text-primary">22</h4>
                                <p class="text-muted">Total Days</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-success">20</h4>
                                <p class="text-muted">Present</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-warning">3</h4>
                                <p class="text-muted">Late Arrivals</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-danger">2</h4>
                                <p class="text-muted">Early Departs</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-center">
                                <h4 class="text-info">8.2 hrs</h4>
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
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Late Arrival Alert</h6>
                                <small class="text-muted">2 days ago</small>
                            </div>
                            <p class="mb-1">You arrived 5 minutes late yesterday. Please maintain punctuality.</p>
                            <small class="text-muted">Attendance Management</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Performance Reminder</h6>
                                <small class="text-muted">1 week ago</small>
                            </div>
                            <p class="mb-1">Your punctuality score is 94.2%. Keep up the good work!</p>
                            <small class="text-muted">HR Department</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Monthly Summary Ready</h6>
                                <small class="text-muted">2 weeks ago</small>
                            </div>
                            <p class="mb-1">Your January 2026 attendance summary is available for review.</p>
                            <small class="text-muted">Admin Dashboard</small>
                        </a>
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
    alert('Performance trends would be displayed here');
}

function changeMonth() {
    // Change month logic
    const month = document.getElementById('monthFilter').value;
    console.log('Month changed to: ' + month);
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
    
    // Simulate download
    setTimeout(() => {
        // Hide modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('downloadReportModal'));
        modal.hide();
        
        // Show success
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        alert(`Report downloaded successfully!\nType: ${reportType}\nFormat: ${format}`);
    }, 2000);
}
</script>
@endsection