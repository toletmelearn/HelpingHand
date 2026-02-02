@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Lesson Plan Reports</h1>
            <p class="mb-4">View reports and analytics for lesson plans</p>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">{{ $totalLessonPlans }}</h5>
                            <p class="card-text">Total Lesson Plans</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">{{ $weeklyPlans }}</h5>
                            <p class="card-text">This Week</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">{{ $monthlyPlans }}</h5>
                            <p class="card-text">This Month</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Lesson Plans by Teacher</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Total Plans</th>
                                    <th>Daily Plans</th>
                                    <th>Weekly Plans</th>
                                    <th>Monthly Plans</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachersWithPlans as $teacher)
                                    <tr>
                                        <td>{{ $teacher->name }}</td>
                                        <td>{{ $teacher->lesson_plans_count }}</td>
                                        <td>{{ $teacher->lessonPlans->where('plan_type', 'daily')->count() }}</td>
                                        <td>{{ $teacher->lessonPlans->where('plan_type', 'weekly')->count() }}</td>
                                        <td>{{ $teacher->lessonPlans->where('plan_type', 'monthly')->count() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No lesson plans found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lesson Plan Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="planTypeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('planTypeChart').getContext('2d');
    
    // Calculate counts for different plan types
    const dailyCount = @json(collect($teachersWithPlans)->sum(function($teacher) { 
        return $teacher->lessonPlans->where('plan_type', 'daily')->count(); 
    }));
    const weeklyCount = @json(collect($teachersWithPlans)->sum(function($teacher) { 
        return $teacher->lessonPlans->where('plan_type', 'weekly')->count(); 
    }));
    const monthlyCount = @json(collect($teachersWithPlans)->sum(function($teacher) { 
        return $teacher->lessonPlans->where('plan_type', 'monthly')->count(); 
    }));
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Daily Plans', 'Weekly Plans', 'Monthly Plans'],
            datasets: [{
                data: [dailyCount, weeklyCount, monthlyCount],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
});
</script>
@endsection
