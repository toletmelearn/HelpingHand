@extends('layouts.admin')

@section('title', 'Analytics Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-bar-chart-line"></i> Analytics Dashboard
            </h1>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="dateRange" class="form-label">Date Range</label>
                            <input type="date" class="form-control" id="dateRange">
                        </div>
                        <div class="col-md-3">
                            <label for="departmentFilter" class="form-label">Department</label>
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <option value="math">Mathematics</option>
                                <option value="science">Science</option>
                                <option value="english">English</option>
                                <option value="history">History</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="teacherFilter" class="form-label">Teacher</label>
                            <select class="form-select" id="teacherFilter">
                                <option value="">All Teachers</option>
                                <option value="1">John Doe</option>
                                <option value="2">Jane Smith</option>
                                <option value="3">Robert Johnson</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="on_time">On Time</option>
                                <option value="late">Late Arrival</option>
                                <option value="early_exit">Early Departure</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                        <button class="btn btn-secondary" onclick="resetFilters()">Reset</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">45</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people fa-2x text-gray-300"></i>
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
                                Average Attendance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">94.2%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
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
                                Late Arrivals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">12</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Early Departures</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">8</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-door-open fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Attendance Trend Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Trend</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="attendanceChartDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="attendanceChartDropdown">
                            <div class="dropdown-header">Chart Options:</div>
                            <a class="dropdown-item" href="#">Daily</a>
                            <a class="dropdown-item" href="#">Weekly</a>
                            <a class="dropdown-item" href="#">Monthly</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Export Data</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="attendanceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Distribution -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Distribution</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="attendanceDistributionDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="attendanceDistributionDropdown">
                            <div class="dropdown-header">Options:</div>
                            <a class="dropdown-item" href="#">By Status</a>
                            <a class="dropdown-item" href="#">By Department</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Export Data</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="attendanceDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> On Time
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> Late
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-danger"></i> Early Exit
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-secondary"></i> Absent
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Heatmap and Performance Charts -->
    <div class="row mb-4">
        <!-- Attendance Heatmap -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Heatmap</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="attendanceHeatmapChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Index -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Index</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="performanceIndexChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers and Recent Activity -->
    <div class="row">
        <!-- Top Performers -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Performers</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Punctuality</th>
                                    <th>Discipline</th>
                                    <th>Consistency</th>
                                    <th>Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>John Doe</td>
                                    <td><span class="badge bg-success">98.5%</span></td>
                                    <td><span class="badge bg-success">97.2%</span></td>
                                    <td><span class="badge bg-success">96.8%</span></td>
                                    <td><span class="badge bg-success">97.5%</span></td>
                                </tr>
                                <tr>
                                    <td>Jane Smith</td>
                                    <td><span class="badge bg-success">96.2%</span></td>
                                    <td><span class="badge bg-success">95.8%</span></td>
                                    <td><span class="badge bg-success">94.5%</span></td>
                                    <td><span class="badge bg-success">95.5%</span></td>
                                </tr>
                                <tr>
                                    <td>Robert Johnson</td>
                                    <td><span class="badge bg-success">94.8%</span></td>
                                    <td><span class="badge bg-success">93.2%</span></td>
                                    <td><span class="badge bg-success">92.7%</span></td>
                                    <td><span class="badge bg-success">93.6%</span></td>
                                </tr>
                                <tr>
                                    <td>Emily Davis</td>
                                    <td><span class="badge bg-warning">89.5%</span></td>
                                    <td><span class="badge bg-success">91.2%</span></td>
                                    <td><span class="badge bg-warning">88.8%</span></td>
                                    <td><span class="badge bg-warning">89.8%</span></td>
                                </tr>
                                <tr>
                                    <td>Michael Wilson</td>
                                    <td><span class="badge bg-danger">78.2%</span></td>
                                    <td><span class="badge bg-warning">85.5%</span></td>
                                    <td><span class="badge bg-danger">82.1%</span></td>
                                    <td><span class="badge bg-warning">81.9%</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">John Doe</h5>
                                <small class="text-muted">Today 08:45 AM</small>
                            </div>
                            <p class="mb-1">Arrived <span class="text-success">on time</span> for duty</p>
                            <small class="text-muted">Working hours: 8.5 hours</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Jane Smith</h5>
                                <small class="text-muted">Today 08:55 AM</small>
                            </div>
                            <p class="mb-1">Arrived <span class="text-warning">5 minutes late</span></p>
                            <small class="text-muted">Working hours: 7.8 hours</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Robert Johnson</h5>
                                <small class="text-muted">Today 08:30 AM</small>
                            </div>
                            <p class="mb-1">Arrived <span class="text-success">early</span> for duty</p>
                            <small class="text-muted">Working hours: 9.2 hours</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Emily Davis</h5>
                                <small class="text-muted">Yesterday 09:15 AM</small>
                            </div>
                            <p class="mb-1">Arrived <span class="text-warning">15 minutes late</span></p>
                            <small class="text-muted">Working hours: 8.0 hours</small>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Michael Wilson</h5>
                                <small class="text-muted">Yesterday 08:50 AM</small>
                            </div>
                            <p class="mb-1">Arrived <span class="text-warning">10 minutes late</span></p>
                            <small class="text-muted">Left early: 4.5 hours</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Attendance Trend Chart
    var ctx = document.getElementById("attendanceTrendChart");
    var attendanceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Jan 1", "Jan 7", "Jan 14", "Jan 21", "Jan 28"],
            datasets: [{
                label: "Attendance %",
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [92, 94, 95, 93, 94],
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                },
                y: {
                    grid: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    },
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10,
                    callbacks: {
                        label: function(context) {
                            var label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += Math.round(context.parsed.y * 100) / 100 + '%';
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Attendance Distribution Chart
    var ctx2 = document.getElementById("attendanceDistributionChart");
    var attendanceDistributionChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ["On Time", "Late", "Early Exit", "Absent"],
            datasets: [{
                data: [65, 20, 10, 5],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                hoverBackgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            cutout: '70%',
        }
    });

    // Performance Index Chart
    var ctx3 = document.getElementById("performanceIndexChart");
    var performanceIndexChart = new Chart(ctx3, {
        type: 'radar',
        data: {
            labels: ['Punctuality', 'Discipline', 'Consistency', 'Attendance', 'Working Hours'],
            datasets: [{
                label: 'Average Performance',
                backgroundColor: 'rgba(78, 115, 223, 0.2)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                data: [85, 88, 82, 90, 87]
            }]
        },
        options: {
            scale: {
                ticks: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
});

function applyFilters() {
    // Apply filters logic
    console.log('Filters applied');
}

function resetFilters() {
    // Reset filters logic
    document.getElementById('dateRange').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('teacherFilter').value = '';
    document.getElementById('statusFilter').value = '';
    console.log('Filters reset');
}
</script>
@endsection
