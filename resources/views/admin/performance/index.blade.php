@extends('layouts.admin')

@section('title', 'Performance Analytics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-award"></i> Performance Analytics Dashboard
            </h1>
        </div>
    </div>

    <!-- Performance Metrics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Avg Punctuality</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">87.5%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
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
                                Avg Discipline</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">89.2%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-shield-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Avg Consistency</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">85.7%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-reception-4 fa-2x text-gray-300"></i>
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
                                Avg Working Hrs</div>
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
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Avg Late Mins</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">12.5</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-hourglass-bottom fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Performance Grade</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">B+</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-star-half fa-2x text-gray-300"></i>
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
                    <button class="btn btn-primary" onclick="calculateAllScores()">
                        <i class="bi bi-calculator"></i> Calculate All Scores
                    </button>
                    <button class="btn btn-success" onclick="exportPerformance()">
                        <i class="bi bi-file-earmark-arrow-down"></i> Export Performance
                    </button>
                </div>
                
                <div>
                    <select class="form-select" id="periodFilter">
                        <option value="daily">Daily</option>
                        <option value="weekly" selected>Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="row mb-4">
        <!-- Performance Trend -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Trend</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="performanceTrendDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="performanceTrendDropdown">
                            <div class="dropdown-header">Chart Options:</div>
                            <a class="dropdown-item" href="#">Punctuality</a>
                            <a class="dropdown-item" href="#">Discipline</a>
                            <a class="dropdown-item" href="#">Consistency</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Export Chart</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="performanceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Distribution -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Grades</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="performanceGradesDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="performanceGradesDropdown">
                            <div class="dropdown-header">Options:</div>
                            <a class="dropdown-item" href="#">By Grade</a>
                            <a class="dropdown-item" href="#">By Department</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Export Data</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="performanceGradesChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> A+
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> A
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> B+
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> B
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-danger"></i> C/D
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers and Underperformers -->
    <div class="row mb-4">
        <!-- Top Performers -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Top Performers</h6>
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
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>John Doe</td>
                                    <td><span class="badge bg-success">98.5%</span></td>
                                    <td><span class="badge bg-success">97.2%</span></td>
                                    <td><span class="badge bg-success">96.8%</span></td>
                                    <td><span class="badge bg-success">97.5%</span></td>
                                    <td><span class="badge bg-success">A+</span></td>
                                </tr>
                                <tr>
                                    <td>Jane Smith</td>
                                    <td><span class="badge bg-success">96.2%</span></td>
                                    <td><span class="badge bg-success">95.8%</span></td>
                                    <td><span class="badge bg-success">94.5%</span></td>
                                    <td><span class="badge bg-success">95.5%</span></td>
                                    <td><span class="badge bg-success">A</span></td>
                                </tr>
                                <tr>
                                    <td>Robert Johnson</td>
                                    <td><span class="badge bg-success">94.8%</span></td>
                                    <td><span class="badge bg-success">93.2%</span></td>
                                    <td><span class="badge bg-success">92.7%</span></td>
                                    <td><span class="badge bg-success">93.6%</span></td>
                                    <td><span class="badge bg-primary">B+</span></td>
                                </tr>
                                <tr>
                                    <td>Emily Davis</td>
                                    <td><span class="badge bg-warning">89.5%</span></td>
                                    <td><span class="badge bg-success">91.2%</span></td>
                                    <td><span class="badge bg-warning">88.8%</span></td>
                                    <td><span class="badge bg-warning">89.8%</span></td>
                                    <td><span class="badge bg-primary">B+</span></td>
                                </tr>
                                <tr>
                                    <td>Michael Wilson</td>
                                    <td><span class="badge bg-warning">87.2%</span></td>
                                    <td><span class="badge bg-warning">86.5%</span></td>
                                    <td><span class="badge bg-warning">85.1%</span></td>
                                    <td><span class="badge bg-warning">86.3%</span></td>
                                    <td><span class="badge bg-primary">B+</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Underperformers -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">Underperformers</h6>
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
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>David Brown</td>
                                    <td><span class="badge bg-danger">68.2%</span></td>
                                    <td><span class="badge bg-warning">75.5%</span></td>
                                    <td><span class="badge bg-danger">72.1%</span></td>
                                    <td><span class="badge bg-danger">71.9%</span></td>
                                    <td><span class="badge bg-danger">C</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="Review">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-warning" title="Counsel">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sarah Miller</td>
                                    <td><span class="badge bg-danger">72.5%</span></td>
                                    <td><span class="badge bg-danger">69.8%</span></td>
                                    <td><span class="badge bg-warning">74.3%</span></td>
                                    <td><span class="badge bg-danger">72.2%</span></td>
                                    <td><span class="badge bg-danger">C</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="Review">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-warning" title="Counsel">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>James Taylor</td>
                                    <td><span class="badge bg-danger">76.8%</span></td>
                                    <td><span class="badge bg-danger">73.2%</span></td>
                                    <td><span class="badge bg-danger">69.5%</span></td>
                                    <td><span class="badge bg-warning">73.2%</span></td>
                                    <td><span class="badge bg-warning">C+</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="Review">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-warning" title="Counsel">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Lisa Anderson</td>
                                    <td><span class="badge bg-warning">81.2%</span></td>
                                    <td><span class="badge bg-danger">78.5%</span></td>
                                    <td><span class="badge bg-warning">80.1%</span></td>
                                    <td><span class="badge bg-warning">79.9%</span></td>
                                    <td><span class="badge bg-warning">B-</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="Review">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-warning" title="Counsel">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Monitor">
                                                <i class="bi bi-binoculars"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Thomas Jackson</td>
                                    <td><span class="badge bg-warning">83.5%</span></td>
                                    <td><span class="badge bg-warning">80.2%</span></td>
                                    <td><span class="badge bg-warning">78.8%</span></td>
                                    <td><span class="badge bg-warning">80.8%</span></td>
                                    <td><span class="badge bg-warning">B-</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-primary" title="Review">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-warning" title="Counsel">
                                                <i class="bi bi-chat-dots"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-info" title="Monitor">
                                                <i class="bi bi-binoculars"></i>
                                            </a>
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

    <!-- Performance Insights -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Insights</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-success">Positive Trends</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle-fill text-success"></i> Overall punctuality improved by 3.2%</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> Consistency scores increased by 2.1%</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> Average working hours increased to 8.2</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> Early departures reduced by 15%</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-warning">Areas for Improvement</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-exclamation-circle-fill text-warning"></i> Late arrivals increased by 2.5%</li>
                                <li><i class="bi bi-exclamation-circle-fill text-warning"></i> 12 teachers below 80% performance</li>
                                <li><i class="bi bi-exclamation-circle-fill text-warning"></i> Half-day absences up by 8%</li>
                                <li><i class="bi bi-exclamation-circle-fill text-warning"></i> Weekend attendance needs attention</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-info">Recommendations</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-lightbulb-fill text-info"></i> Implement early bird incentive program</li>
                                <li><i class="bi bi-lightbulb-fill text-info"></i> Organize punctuality awareness workshops</li>
                                <li><i class="bi bi-lightbulb-fill text-info"></i> Set up mentorship for underperformers</li>
                                <li><i class="bi bi-lightbulb-fill text-info"></i> Review working hour policies</li>
                            </ul>
                        </div>
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
    // Performance Trend Chart
    var ctx = document.getElementById("performanceTrendChart");
    var performanceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Week 1", "Week 2", "Week 3", "Week 4", "Week 5"],
            datasets: [{
                label: "Punctuality Score",
                lineTension: 0.3,
                backgroundColor: "rgba(40, 167, 69, 0.05)",
                borderColor: "rgba(40, 167, 69, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(40, 167, 69, 1)",
                pointBorderColor: "rgba(40, 167, 69, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(40, 167, 69, 1)",
                pointHoverBorderColor: "rgba(40, 167, 69, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [82, 84, 85, 87, 87.5],
            }, {
                label: "Discipline Score",
                lineTension: 0.3,
                backgroundColor: "rgba(25, 135, 84, 0.05)",
                borderColor: "rgba(25, 135, 84, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(25, 135, 84, 1)",
                pointBorderColor: "rgba(25, 135, 84, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(25, 135, 84, 1)",
                pointHoverBorderColor: "rgba(25, 135, 84, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [85, 86, 87, 88, 89.2],
            }, {
                label: "Consistency Score",
                lineTension: 0.3,
                backgroundColor: "rgba(13, 110, 253, 0.05)",
                borderColor: "rgba(13, 110, 253, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(13, 110, 253, 1)",
                pointBorderColor: "rgba(13, 110, 253, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(13, 110, 253, 1)",
                pointHoverBorderColor: "rgba(13, 110, 253, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [82, 83, 84, 85, 85.7],
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
                    display: true,
                    position: 'top'
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

    // Performance Grades Chart
    var ctx2 = document.getElementById("performanceGradesChart");
    var performanceGradesChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ["A+", "A", "B+", "B", "C/D"],
            datasets: [{
                data: [8, 12, 15, 10, 5],
                backgroundColor: ['#28a745', '#17a2b8', '#007bff', '#ffc107', '#dc3545'],
                hoverBackgroundColor: ['#28a745', '#17a2b8', '#007bff', '#ffc107', '#dc3545'],
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
});

function calculateAllScores() {
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Calculating...';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Calculation Complete';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 3000);
    }, 2000);
}

function exportPerformance() {
    // Simulate export
    alert('Export functionality would be implemented here');
}
</script>
@endsection
