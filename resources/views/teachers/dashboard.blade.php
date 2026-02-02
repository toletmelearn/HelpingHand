@extends('layouts.admin')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="container mt-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            ðŸ‘¨â€ðŸ« Teachers Dashboard
            <small class="text-muted fs-6">Staff Analytics</small>
        </h1>
        <div>
            <a href="{{ url('/teachers') }}" class="btn btn-outline-info">
                ðŸ“‹ Teacher List
            </a>
            <a href="{{ url('/teachers/create') }}" class="btn btn-success ms-2">   
                âž• Add Teacher
            </a>
            <a href="{{ route('teacher.results.index') }}" class="btn btn-primary ms-2">
                ðŸ“Š My Results
            </a>
            <a href="{{ route('teachers.biometric.dashboard') }}" class="btn btn-warning ms-2">
                ðŸ• My Attendance
            </a>
        </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['total'] }}</h1>
                        <h6>Total Teachers</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['male'] }}</h1>
                        <h6>Male Teachers</h6>
                        <small>{{ $stats['total'] > 0 ? round(($stats['male']/$stats['total'])*100, 2) : 0 }}%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['female'] }}</h1>
                        <h6>Female Teachers</h6>
                        <small>{{ $stats['total'] > 0 ? round(($stats['female']/$stats['total'])*100, 2) : 0 }}%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wing-wise Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card wing-card-primary h-100">
                    <div class="card-body">
                        <h5>ðŸ« Primary Wing</h5>
                        <h2>{{ $stats['wing_wise']['primary']['total'] }}</h2>
                        <div class="small">
                            <span class="text-primary">â™‚ {{ $stats['wing_wise']['primary']['male'] }}</span> | 
                            <span class="text-info">â™€ {{ $stats['wing_wise']['primary']['female'] }}</span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-primary">PRT: {{ $stats['wing_wise']['primary']['PRT'] }}</span>
                            <span class="badge bg-info ms-1">TGT: {{ $stats['wing_wise']['primary']['TGT'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card wing-card-junior h-100">
                    <div class="card-body">
                        <h5>ðŸ“š Junior Wing</h5>
                        <h2>{{ $stats['wing_wise']['junior']['total'] }}</h2>
                        <div class="small">
                            <span class="text-primary">â™‚ {{ $stats['wing_wise']['junior']['male'] }}</span> | 
                            <span class="text-info">â™€ {{ $stats['wing_wise']['junior']['female'] }}</span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-primary">PRT: {{ $stats['wing_wise']['junior']['PRT'] }}</span>
                            <span class="badge bg-info ms-1">TGT: {{ $stats['wing_wise']['junior']['TGT'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card wing-card-senior h-100">
                    <div class="card-body">
                        <h5>ðŸŽ“ Senior Wing</h5>
                        <h2>{{ $stats['wing_wise']['senior']['total'] }}</h2>
                        <div class="small">
                            <span class="text-primary">â™‚ {{ $stats['wing_wise']['senior']['male'] }}</span> | 
                            <span class="text-info">â™€ {{ $stats['wing_wise']['senior']['female'] }}</span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-info">TGT: {{ $stats['wing_wise']['senior']['TGT'] }}</span>
                            <span class="badge bg-success ms-1">PGT: {{ $stats['wing_wise']['senior']['PGT'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teacher Type Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">ðŸ“‹ Teacher Type Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($stats['type_wise'] as $type => $data)
                    <div class="col-md-3 mb-3">
                        <div class="card type-card 
                            {{ $type == 'PRT' ? 'bg-primary' : '' }}
                            {{ $type == 'TGT' ? 'bg-info' : '' }}
                            {{ $type == 'PGT' ? 'bg-success' : '' }}
                            {{ $type == 'Other' ? 'bg-warning' : '' }}
                            text-white">
                            <div class="card-body text-center">
                                <h5>{{ $type }}</h5>
                                <h2>{{ $data['total'] }}</h2>
                                <div class="small">
                                    <span>â™‚ {{ $data['male'] }}</span> | 
                                    <span>â™€ {{ $data['female'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">ðŸ“Š Gender Distribution by Wing</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="genderWingChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">ðŸ“ˆ Teacher Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typeChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gender by Wing Chart
        const genderWingCtx = document.getElementById('genderWingChart').getContext('2d');
        new Chart(genderWingCtx, {
            type: 'bar',
            data: {
                labels: ['Primary', 'Junior', 'Senior'],
                datasets: [
                    {
                        label: 'Male',
                        data: [
                            {{ $stats['gender_wing_wise']['primary']['male'] }},
                            {{ $stats['gender_wing_wise']['junior']['male'] }},
                            {{ $stats['gender_wing_wise']['senior']['male'] }}
                        ],
                        backgroundColor: '#28a745'
                    },
                    {
                        label: 'Female',
                        data: [
                            {{ $stats['gender_wing_wise']['primary']['female'] }},
                            {{ $stats['gender_wing_wise']['junior']['female'] }},
                            {{ $stats['gender_wing_wise']['senior']['female'] }}
                        ],
                        backgroundColor: '#17a2b8'
                    }
                ]
            },
            options: {
                responsive: true
            }
        });

        // Teacher Type Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    @foreach($stats['type_wise'] as $type => $data)
                        '{{ $type }}',
                    @endforeach
                ],
                datasets: [{
                    data: [
                        @foreach($stats['type_wise'] as $type => $data)
                            {{ $data['total'] }},
                        @endforeach
                    ],
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#28a745'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</div>
@endsection
