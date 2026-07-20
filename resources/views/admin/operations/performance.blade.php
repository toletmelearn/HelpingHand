@extends('layouts.admin')

@section('title', 'Performance Dashboard')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Performance</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-graph-up-arrow text-primary me-2"></i> Performance Observability</h3>
            <p class="text-muted">Monitor database sizes, request response times, cache utilization ratios, and trace slow database queries.</p>
        </div>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <!-- Average response time -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Avg HTTP Response Time</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['avg_response_time_ms'] }} ms</h3>
                        <small class="text-muted">Measured sliding request</small>
                    </div>
                    <div class="p-3 rounded bg-primary-subtle text-primary"><i class="bi bi-speedometer"></i></div>
                </div>
            </div>
        </div>

        <!-- CPU Usage -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">CPU Utilization</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['cpu_usage'] }}%</h3>
                        <small class="text-muted">System processor usage</small>
                    </div>
                    <div class="p-3 rounded bg-info-subtle text-info"><i class="bi bi-cpu"></i></div>
                </div>
            </div>
        </div>

        <!-- Cache Hit Ratio -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Cache Hit Ratio</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-success">{{ $metrics['cache_hit_ratio'] }}%</h3>
                        <small class="text-muted">Database cache query bypass</small>
                    </div>
                    <div class="p-3 rounded bg-success-subtle text-success"><i class="bi bi-lightning-charge"></i></div>
                </div>
            </div>
        </div>

        <!-- DB footprint size -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Database Footprint</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['db_size'] }}</h3>
                        <small class="text-muted">Total tables storage</small>
                    </div>
                    <div class="p-3 rounded bg-secondary-subtle text-secondary"><i class="bi bi-database"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Integrations Latencies -->
    <div class="row g-3 mb-4">
        <!-- Stripe API Latency -->
        <div class="col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Stripe Payment Latency</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['stripe_response_time_ms'] }} ms</h3>
                        <small class="text-muted">Payment gateway endpoint latency</small>
                    </div>
                    <div class="p-3 rounded bg-warning-subtle text-warning"><i class="bi bi-credit-card"></i></div>
                </div>
            </div>
        </div>

        <!-- Import processing speed -->
        <div class="col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Student Import Throughput</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['import_speed_per_second'] }} rec/s</h3>
                        <small class="text-muted">Bulk student migration engine speed</small>
                    </div>
                    <div class="p-3 rounded bg-success-subtle text-success"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slow Query Log Table -->
    <div class="card glass-card border-0">
        <div class="card-header bg-light border-0 p-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-hourglass-bottom me-1 text-danger"></i> Database Slow Query Log Trace</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50%;">SQL Query Statement</th>
                            <th style="width: 20%;">Bindings</th>
                            <th style="width: 15%;">Query Runtime</th>
                            <th style="width: 15%;">Logged At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics['slow_queries'] as $query)
                            <tr>
                                <td>
                                    <code class="text-danger small bg-light px-2 py-1 rounded border d-block text-truncate" style="max-width: 500px;" title="{{ $query['sql'] }}">
                                        {{ $query['sql'] }}
                                    </code>
                                </td>
                                <td class="small text-muted">
                                    {{ !empty($query['bindings']) ? json_encode($query['bindings']) : '[]' }}
                                </td>
                                <td>
                                    <span class="badge bg-danger-subtle text-danger border border-danger">
                                        {{ $query['time'] }} ms
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $query['logged_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-lightning-charge fs-1 d-block mb-2 text-success"></i>
                                    Excellent! No query executions took longer than 50ms.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
