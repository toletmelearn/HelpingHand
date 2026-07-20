@extends('layouts.admin')

@section('title', 'Queue Monitoring Center')

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
                    <li class="breadcrumb-item active" aria-current="page">Queue Monitor</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-cpu text-info me-2"></i> Queue Monitoring Center</h3>
            <p class="text-muted">Observe active queue sizes, background worker status, execution benchmarks, and manage failed job reprocessing pipelines.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Queue Metrics -->
    <div class="row g-3 mb-4">
        <!-- Worker status -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Worker Status</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-{{ $stats['worker_status'] === 'Running' ? 'success' : 'warning' }}">{{ $stats['worker_status'] }}</h3>
                        <small class="text-muted">Driver: {{ config('queue.default') }}</small>
                    </div>
                    <div class="p-3 rounded bg-{{ $stats['worker_status'] === 'Running' ? 'success' : 'warning' }}-subtle text-{{ $stats['worker_status'] === 'Running' ? 'success' : 'warning' }}"><i class="bi bi-activity"></i></div>
                </div>
            </div>
        </div>

        <!-- Pending Backlog -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Pending Backlog</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $stats['pending_jobs'] }}</h3>
                        <small class="text-muted">Jobs in database</small>
                    </div>
                    <div class="p-3 rounded bg-primary-subtle text-primary"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>

        <!-- Failed jobs -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Failed Records</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-{{ $stats['failed_jobs'] > 0 ? 'danger' : 'success' }}">{{ $stats['failed_jobs'] }}</h3>
                        <small class="text-muted">Awaiting retry</small>
                    </div>
                    <div class="p-3 rounded bg-{{ $stats['failed_jobs'] > 0 ? 'danger' : 'success' }}-subtle text-{{ $stats['failed_jobs'] > 0 ? 'danger' : 'success' }}"><i class="bi bi-x-octagon"></i></div>
                </div>
            </div>
        </div>

        <!-- Average Runtime -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Average Runtime</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $stats['average_runtime'] }}</h3>
                        <small class="text-muted">Per job execution</small>
                    </div>
                    <div class="p-3 rounded bg-info-subtle text-info"><i class="bi bi-stopwatch"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Failed Jobs Section -->
    <div class="card glass-card border-0">
        <div class="card-header bg-light border-0 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-exclamation-octagon-fill me-1 text-danger"></i> Failed Queue Jobs Audit Log</h6>
            <div class="d-flex gap-2">
                @if($stats['failed_jobs'] > 0)
                    <form action="{{ route('operations.queue.retry-all') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i> Retry All Jobs</button>
                    </form>
                    <form action="{{ route('operations.queue.clear-failed') }}" method="POST" onsubmit="return confirm('Clear failed jobs list permanently?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> Clear Failed Queue</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">ID</th>
                            <th style="width: 15%;">Queue / Conn</th>
                            <th style="width: 25%;">Failed Job Payload</th>
                            <th style="width: 30%;">Exception Summary</th>
                            <th style="width: 10%;">Failed At</th>
                            <th style="width: 10%;" class="text-end">Retry</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($failedJobs as $job)
                            <tr>
                                <td><span class="text-muted small">#{{ $job['id'] }}</span></td>
                                <td>
                                    <span class="badge bg-secondary mb-1 d-block text-truncate" style="max-width: 120px;">{{ $job['queue'] }}</span>
                                    <span class="text-muted small">{{ $job['connection'] }}</span>
                                </td>
                                <td>
                                    <strong class="text-dark d-block text-truncate" style="max-width: 220px;" title="{{ $job['name'] }}">{{ $job['name'] }}</strong>
                                </td>
                                <td>
                                    <div class="text-danger small text-truncate" style="max-width: 280px;" title="{{ $job['exception'] }}">
                                        {{ $job['exception'] }}
                                    </div>
                                </td>
                                <td class="small text-muted">{{ $job['failed_at'] }}</td>
                                <td class="text-end">
                                    <form action="{{ route('operations.queue.retry', $job['id']) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary" title="Re-queue Job">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-emoji-smile fs-1 d-block mb-2 text-success"></i>
                                    Excellent! No failed background queue jobs found.
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
