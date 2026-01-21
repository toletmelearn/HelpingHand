<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Experience Calculator - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .experience-card { border-left: 4px solid #007bff; }
        .retirement-card { border-left: 4px solid #dc3545; }
        .new-teacher-card { border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                📅 Teacher Experience Calculator
                <small class="text-muted fs-6">Comprehensive experience analytics</small>
            </h1>
            <div>
                <a href="{{ url('/teachers') }}" class="btn btn-outline-primary">
                    ← Back to Teachers
                </a>
                <a href="{{ url('/teachers-dashboard') }}" class="btn btn-info ms-2">
                    📊 Dashboard
                </a>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card experience-card">
                    <div class="card-body">
                        <h6>Total Experience</h6>
                        <h3>{{ floor($experienceStats['total_experience_months'] / 12) }} years</h3>
                        <small>{{ $experienceStats['total_experience_months'] }} months combined</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Average Experience</h6>
                        <h3>{{ $experienceStats['average_experience'] }} years</h3>
                        <small>Per teacher</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card retirement-card">
                    <div class="card-body">
                        <h6>Retiring Soon</h6>
                        <h3 class="text-danger">{{ $experienceStats['retiring_soon'] }}</h3>
                        <small>Within 5 years</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card new-teacher-card">
                    <div class="card-body">
                        <h6>New Teachers</h6>
                        <h3 class="text-success">{{ $experienceStats['new_teachers'] }}</h3>
                        <small>Less than 1 year</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Experience Distribution Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">📊 Experience Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="experienceChart" height="150"></canvas>
            </div>
        </div>
        
        <!-- Teacher Experience Details -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">👨‍🏫 Teacher Experience Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Teacher</th>
                                <th>Joining Date</th>
                                <th>Current Experience</th>
                                <th>Previous Experience</th>
                                <th>Total Experience</th>
                                <th>Retirement Date</th>
                                <th>Years Left</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teachers as $teacher)
                            <tr>
                                <td>
                                    <strong>{{ $teacher->name }}</strong>
                                    <div class="small text-muted">{{ $teacher->designation }}</div>
                                </td>
                                <td>
                                    @if($teacher->date_of_joining)
                                        {{ \Carbon\Carbon::parse($teacher->date_of_joining)->format('d M Y') }}
                                        <div class="small text-muted">
                                            ({{ \Carbon\Carbon::parse($teacher->date_of_joining)->diffForHumans() }})
                                        </div>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $teacher->total_experience }}</span>
                                </td>
                                <td>
                                    @if($teacher->previous_experience)
                                        <span class="badge bg-secondary">{{ $teacher->previous_experience }}</span>
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-success">{{ $teacher->overall_experience }}</span>
                                </td>
                                <td>
                                    @if($teacher->years_until_retirement !== null)
                                        @php
                                            $yearsLeft = $teacher->years_until_retirement;
                                            $badgeClass = $yearsLeft <= 3 ? 'bg-danger' : 
                                                         ($yearsLeft <= 7 ? 'bg-warning' : 'bg-success');
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">
                                            {{ $teacher->retirement_date }}
                                        </span>
                                        <div class="small">
                                            {{ $yearsLeft > 0 ? $yearsLeft . ' years left' : 'Retired' }}
                                        </div>
                                    @else
                                        <span class="text-muted">DOB not set</span>
                                    @endif
                                </td>
                                <td>
                                    @if($teacher->years_until_retirement !== null)
                                        @php
                                            $yearsLeft = $teacher->years_until_retirement;
                                            $progressClass = $yearsLeft <= 3 ? 'bg-danger' : 
                                                           ($yearsLeft <= 7 ? 'bg-warning' : 'bg-success');
                                            $progressWidth = min(100, max(0, (60 - $yearsLeft) / 60 * 100));
                                        @endphp
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar {{ $progressClass }}" 
                                                 style="width: {{ $progressWidth }}%">
                                                {{ $yearsLeft }} years
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ url('/teachers/' . $teacher->id . '/experience-certificate') }}" 
                                       class="btn btn-sm btn-outline-primary" 
                                       target="_blank">
                                        📄 Certificate
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Experience Distribution Chart
        const ctx = document.getElementById('experienceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['0-5 years', '6-10 years', '11-15 years', '16-20 years', '20+ years'],
                datasets: [{
                    label: 'Number of Teachers',
                    data: [
                        {{ $experienceStats['by_range']['0-5'] }},
                        {{ $experienceStats['by_range']['6-10'] }},
                        {{ $experienceStats['by_range']['11-15'] }},
                        {{ $experienceStats['by_range']['16-20'] }},
                        {{ $experienceStats['by_range']['20+'] }}
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#ffc107',
                        '#fd7e14',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>