@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Budget Dashboard</li>
                    </ol>
                </div>
                <h4 class="page-title">Budget Management Dashboard</h4>
            </div>
        </div>
    </div>

    <!-- Year Filter -->
    <div class="row mb-3">
        <div class="col-md-12">
            <form method="GET" action="{{ route('budget.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="year" class="form-label">Fiscal Year</label>
                    <select name="year" id="year" class="form-select">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $currentYear == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-lg rounded bg-light">
                                <i class="fas fa-wallet avatar-title font-22 text-primary"></i>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1">{{ number_format($stats['total_budgets']) }}</h3>
                                <p class="text-muted mb-0">Total Budgets</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-lg rounded bg-light">
                                <i class="fas fa-check-circle avatar-title font-22 text-success"></i>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1">{{ number_format($stats['active_budgets']) }}</h3>
                                <p class="text-muted mb-0">Active Budgets</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-lg rounded bg-light">
                                <i class="fas fa-tag avatar-title font-22 text-info"></i>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1">{{ number_format($stats['total_categories']) }}</h3>
                                <p class="text-muted mb-0">Categories</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-lg rounded bg-light">
                                <i class="fas fa-exclamation-triangle avatar-title font-22 text-warning"></i>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1">{{ number_format($stats['over_budget_count']) }}</h3>
                                <p class="text-muted mb-0">Over Budget</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget Utilization Chart -->
    @if(!empty($categoryUtilization))
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Budget Utilization by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="utilizationChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Budget Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Expenses and Monthly Trend -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Budget</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentExpenses as $expense)
                                <tr>
                                    <td>{{ Str::limit($expense->title, 20) }}</td>
                                    <td>{{ $expense->budget->name ?? 'N/A' }}</td>
                                    <td>{{ $expense->category->name ?? 'N/A' }}</td>
                                    <td>₹{{ number_format($expense->amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $expense->status_label == 'Approved' ? 'success' : ($expense->status_label == 'Pending' ? 'warning' : 'danger') }}">
                                            {{ $expense->status_label }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No recent expenses</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Monthly Expense Trend ({{ $currentYear }})</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(!empty($categoryUtilization))
    // Budget Utilization Chart
    const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
    new Chart(utilizationCtx, {
        type: 'bar',
        data: {
            labels: [
                @foreach($categoryUtilization as $item)
                    '{{ addslashes($item['name']) }}',
                @endforeach
            ],
            datasets: [{
                label: 'Allocated',
                data: [
                    @foreach($categoryUtilization as $item)
                        {{ $item['allocated'] }},
                    @endforeach
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Spent',
                data: [
                    @foreach($categoryUtilization as $item)
                        {{ $item['spent'] }},
                    @endforeach
                ],
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Budget Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Draft', 'Approved', 'Locked', 'Closed'],
            datasets: [{
                data: [
                    {{ $budgetStatusDistribution['draft'] ?? 0 }},
                    {{ $budgetStatusDistribution['approved'] ?? 0 }},
                    {{ $budgetStatusDistribution['locked'] ?? 0 }},
                    {{ $budgetStatusDistribution['closed'] ?? 0 }}
                ],
                backgroundColor: [
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)',
                    'rgba(201, 203, 207, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(201, 203, 207, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    @endif

    // Monthly Expense Trend Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Expenses',
                data: [
                    @for($i = 1; $i <= 12; $i++)
                        {{ $monthlyData[$i] }},
                    @endfor
                ],
                fill: false,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>
@endsection