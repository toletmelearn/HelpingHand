@extends('layouts.app')

@section('title', 'Reports & Exports')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-file-earmark-pdf"></i> Reports & Exports
            </h1>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Attendance Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">5</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Performance Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-award fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Working Hours</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">4</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Summary Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">2</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-bar-chart-line fa-2x text-gray-300"></i>
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
                    <button class="btn btn-primary" onclick="openReportGenerator()">
                        <i class="bi bi-plus-circle"></i> Generate New Report
                    </button>
                    <button class="btn btn-success" onclick="openTemplateManager()">
                        <i class="bi bi-file-earmark-medical"></i> Template Manager
                    </button>
                </div>
                
                <div>
                    <button class="btn btn-outline-secondary" onclick="refreshList()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Types -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Report Types</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Attendance Reports -->
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-calendar-check text-primary"></i> Attendance Reports</h5>
                                    <p class="card-text">Detailed attendance records for teachers</p>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Daily Attendance
                                            <span class="badge bg-primary rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Monthly Summary
                                            <span class="badge bg-primary rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Late Arrival Report
                                            <span class="badge bg-primary rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Early Departure Report
                                            <span class="badge bg-primary rounded-pill">PDF/Excel</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-primary btn-sm w-100" onclick="openReport('attendance')">Generate Report</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Reports -->
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-award text-success"></i> Performance Reports</h5>
                                    <p class="card-text">Performance scores and analytics</p>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Individual Performance
                                            <span class="badge bg-success rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Department Comparison
                                            <span class="badge bg-success rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Monthly Trends
                                            <span class="badge bg-success rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Disciplinary Actions
                                            <span class="badge bg-success rounded-pill">PDF/Excel</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-success btn-sm w-100" onclick="openReport('performance')">Generate Report</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Working Hours Reports -->
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-clock-history text-info"></i> Working Hours Reports</h5>
                                    <p class="card-text">Working hours analysis and averages</p>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Daily Hours Summary
                                            <span class="badge bg-info rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Weekly Hours Analysis
                                            <span class="badge bg-info rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Monthly Hours Report
                                            <span class="badge bg-info rounded-pill">PDF/Excel</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Overtime Analysis
                                            <span class="badge bg-info rounded-pill">PDF/Excel</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-info btn-sm w-100" onclick="openReport('working-hours')">Generate Report</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="recentReportsDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="recentReportsDropdown">
                            <div class="dropdown-header">Recent Reports Options:</div>
                            <a class="dropdown-item" href="#">Refresh List</a>
                            <a class="dropdown-item" href="#">Clear History</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#">Delete All</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="recentReportsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Report Name</th>
                                    <th>Type</th>
                                    <th>Date Generated</th>
                                    <th>Format</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Daily Attendance Report</td>
                                    <td><span class="badge bg-primary">Attendance</span></td>
                                    <td>Jan 27, 2026 10:30 AM</td>
                                    <td><span class="badge bg-success">PDF</span></td>
                                    <td><span class="badge bg-success">Generated</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-success" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Share">
                                                <i class="bi bi-share"></i>
                                            </a>
                                            <form action="#" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this report?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Monthly Performance Summary</td>
                                    <td><span class="badge bg-success">Performance</span></td>
                                    <td>Jan 26, 2026 03:45 PM</td>
                                    <td><span class="badge bg-success">Excel</span></td>
                                    <td><span class="badge bg-success">Generated</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-success" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Share">
                                                <i class="bi bi-share"></i>
                                            </a>
                                            <form action="#" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this report?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Working Hours Analysis</td>
                                    <td><span class="badge bg-info">Working Hours</span></td>
                                    <td>Jan 25, 2026 11:20 AM</td>
                                    <td><span class="badge bg-success">PDF</span></td>
                                    <td><span class="badge bg-success">Generated</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-success" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Share">
                                                <i class="bi bi-share"></i>
                                            </a>
                                            <form action="#" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this report?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Late Arrival Report</td>
                                    <td><span class="badge bg-primary">Attendance</span></td>
                                    <td>Jan 24, 2026 09:15 AM</td>
                                    <td><span class="badge bg-success">Excel</span></td>
                                    <td><span class="badge bg-success">Generated</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-success" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Share">
                                                <i class="bi bi-share"></i>
                                            </a>
                                            <form action="#" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this report?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generator Modal -->
<div class="modal fade" id="reportGeneratorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate New Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reportType" class="form-label">Report Type</label>
                                <select class="form-select" id="reportType" required>
                                    <option value="">Select Report Type</option>
                                    <option value="attendance">Attendance Report</option>
                                    <option value="performance">Performance Report</option>
                                    <option value="working-hours">Working Hours Report</option>
                                    <option value="summary">Summary Report</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reportFormat" class="form-label">Output Format</label>
                                <select class="form-select" id="reportFormat" required>
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacherFilter" class="form-label">Teacher Filter</label>
                        <select class="form-select" id="teacherFilter">
                            <option value="">All Teachers</option>
                            <option value="1">John Doe</option>
                            <option value="2">Jane Smith</option>
                            <option value="3">Robert Johnson</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="departmentFilter" class="form-label">Department Filter</label>
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                            <option value="math">Mathematics</option>
                            <option value="science">Science</option>
                            <option value="english">English</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeCharts">
                        <label class="form-check-label" for="includeCharts">
                            Include Charts and Visualizations
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeSummary">
                        <label class="form-check-label" for="includeSummary">
                            Include Executive Summary
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateReport()">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script>
function openReportGenerator() {
    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    
    // Open modal
    var modal = new bootstrap.Modal(document.getElementById('reportGeneratorModal'));
    modal.show();
}

function openTemplateManager() {
    alert('Template Manager would open here');
}

function openReport(reportType) {
    // Set report type in form
    document.getElementById('reportType').value = reportType;
    
    // Set dates
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    
    // Open modal
    var modal = new bootstrap.Modal(document.getElementById('reportGeneratorModal'));
    modal.show();
}

function generateReport() {
    // Get form data
    const reportType = document.getElementById('reportType').value;
    const reportFormat = document.getElementById('reportFormat').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!reportType || !reportFormat || !startDate || !endDate) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    const btn = document.querySelector('#reportGeneratorModal .btn-primary');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Hide modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('reportGeneratorModal'));
        modal.hide();
        
        // Show success
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        alert(`Report generated successfully!\nType: ${reportType}\nFormat: ${reportFormat}\nDate Range: ${startDate} to ${endDate}`);
    }, 2000);
}

function refreshList() {
    // Reload the page to refresh reports list
    location.reload();
}
</script>
@endsection