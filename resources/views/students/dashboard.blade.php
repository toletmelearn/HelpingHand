<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Dashboard - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .class-card { border-left: 4px solid #007bff; }
        .gender-card { border-left: 4px solid #28a745; }
        .category-card { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                📊 Students Dashboard
                <small class="text-muted fs-6">Comprehensive Analytics</small>
            </h1>
            <div>
                <a href="{{ url('/students') }}" class="btn btn-outline-primary">
                    📋 Student List
                </a>
                <a href="{{ url('/students/create') }}" class="btn btn-success ms-2">
                    ➕ Add Student
                </a>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h1 class="display-4">{{ $stats['total'] }}</h1>
                        <h6>Total Students</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h1 class="display-4">{{ $stats['male'] }}</h1>
                        <h6>Male Students</h6>
                        <small>{{ $stats['male_percentage'] }}% of total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h1 class="display-4">{{ $stats['female'] }}</h1>
                        <h6>Female Students</h6>
                        <small>{{ $stats['female_percentage'] }}% of total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h1 class="display-4">{{ $stats['other'] }}</h1>
                        <h6>Other</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📈 Gender Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="genderChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 Category Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class-wise Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">🎓 Class-wise Distribution</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Class</th>
                                <th>Total</th>
                                <th>Male</th>
                                <th>Female</th>
                                <th>Other</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['class_wise'] as $class)
                            <tr>
                                <td><strong>{{ $class->class ?? 'Not Assigned' }}</strong></td>
                                <td>{{ $class->total }}</td>
                                <td>{{ $class->male }}</td>
                                <td>{{ $class->female }}</td>
                                <td>{{ $class->other }}</td>
                                <td>
                                    <a href="{{ url('/students?class=' . urlencode($class->class)) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Students
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category-wise Statistics -->
        <div class="row">
            @foreach($stats['category_wise'] as $category => $data)
            <div class="col-md-3 mb-3">
                <div class="card category-card">
                    <div class="card-body">
                        <h5>{{ $category }}</h5>
                        <h2>{{ $data['total'] }}</h2>
                        <div class="small">
                            <span class="text-primary">♂ {{ $data['male'] }}</span> | 
                            <span class="text-info">♀ {{ $data['female'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <script>
        // Gender Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [{{ $stats['gender_wise']['male'] }}, 
                           {{ $stats['gender_wise']['female'] }}, 
                           {{ $stats['gender_wise']['other'] }}],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: ['General', 'OBC', 'SC', 'ST'],
                datasets: [{
                    label: 'Students by Category',
                    data: [
                        {{ $stats['category_wise']['General']['total'] }},
                        {{ $stats['category_wise']['OBC']['total'] }},
                        {{ $stats['category_wise']['SC']['total'] }},
                        {{ $stats['category_wise']['ST']['total'] }}
                    ],
                    backgroundColor: ['#007bff', '#6c757d', '#dc3545', '#28a745']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>