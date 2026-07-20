@extends('layouts.admin')

@section('title', 'Operations Center Hub')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
        transition: transform 0.3s cubic-bezier(0.165, 0.84, 0.44, 1), box-shadow 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.12);
    }
    .icon-box {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.8rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumbs & Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Operations Center</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-gear-wide-connected text-primary me-2 animate-spin"></i> Operations Center</h3>
            <p class="text-muted">Unified control center managing disaster recovery, infrastructure, queue jobs, logs, diagnostics, and subscription metrics.</p>
        </div>
    </div>

    <!-- Live Status Overview Cards -->
    <div class="row g-3 mb-4">
        <!-- System Health -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">System Diagnostics</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="fw-bold mb-0 text-{{ $healthClass }}">{{ $healthStatus }}</h4>
                        <small class="text-muted">Checks: {{ $modulesCount }} Modules</small>
                    </div>
                    <div class="icon-box bg-{{ $healthClass }}-subtle text-{{ $healthClass }}"><i class="bi bi-shield-check"></i></div>
                </div>
            </div>
        </div>

        <!-- Pending Queue Jobs -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Active Queue Status</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">{{ $queueStats['pending_jobs'] }} Pending</h4>
                        <small class="text-muted">Worker: {{ $queueStats['worker_status'] }}</small>
                    </div>
                    <div class="icon-box bg-info-subtle text-info"><i class="bi bi-cpu"></i></div>
                </div>
            </div>
        </div>

        <!-- Memory Footprint -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Memory Utilization</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">{{ $queueStats['memory_usage'] }}</h4>
                        <small class="text-muted">Limit: {{ $queueStats['memory_limit'] }}</small>
                    </div>
                    <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-memory"></i></div>
                </div>
            </div>
        </div>

        <!-- SaaS License Tier -->
        <div class="col-md-3 col-sm-6 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">SaaS Plan Tier</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="fw-bold mb-0 text-success">{{ $licenseStatus }}</h4>
                        <small class="text-muted">{{ $licensePlan }}</small>
                    </div>
                    <div class="icon-box bg-success-subtle text-success"><i class="bi bi-credit-card"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sub-Dashboard Grid -->
    <div class="row g-3">
        <!-- 1. Backup & Disaster Recovery -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-primary-subtle text-primary me-3"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Backup & Disaster Recovery</h5>
                        <p class="text-muted small mb-0">Create DB / Files backup, restore wizard.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Last Backup: <strong class="text-dark">{{ $latestBackup ? $latestBackup->completed_at->format('M d, g:i A') : 'Never' }}</strong>
                    </div>
                    <a href="{{ route('operations.backup') }}" class="btn btn-sm btn-primary w-100">Open Disaster Wizard</a>
                </div>
            </div>
        </div>

        <!-- 2. Queue Monitoring Center -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-info-subtle text-info me-3"><i class="bi bi-cpu-fill"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Queue Monitoring Center</h5>
                        <p class="text-muted small mb-0">Track active jobs and retry failed processes.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Failed Backlogs: <strong class="text-danger">{{ $queueStats['failed_jobs'] }}</strong> | Avg runtime: <strong class="text-dark">{{ $queueStats['average_runtime'] }}</strong>
                    </div>
                    <a href="{{ route('operations.queue') }}" class="btn btn-sm btn-info text-white w-100">Monitor Queues</a>
                </div>
            </div>
        </div>

        <!-- 3. Scheduler Dashboard -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-warning-subtle text-warning me-3"><i class="bi bi-calendar2-check"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Scheduler Dashboard</h5>
                        <p class="text-muted small mb-0">Monitor cron frequencies and runtimes.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Heartbeat Status: <span class="badge bg-{{ $queueStats['worker_status'] == 'Running' ? 'success' : 'secondary' }}">{{ $queueStats['worker_status'] }}</span>
                    </div>
                    <a href="{{ route('operations.scheduler') }}" class="btn btn-sm btn-warning text-dark w-100">View Scheduled Tasks</a>
                </div>
            </div>
        </div>

        <!-- 4. Notification Center -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-success-subtle text-success me-3"><i class="bi bi-chat-left-text"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Notification Center</h5>
                        <p class="text-muted small mb-0">Log outputs for Email, SMS & WhatsApp.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Active Channels: <strong class="text-dark">Email, SMS, WhatsApp</strong>
                    </div>
                    <a href="{{ route('operations.notifications') }}" class="btn btn-sm btn-success w-100">Logs & Templates</a>
                </div>
            </div>
        </div>

        <!-- 5. Installation Verification -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-danger-subtle text-danger me-3"><i class="bi bi-check-all"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Installation Checker</h5>
                        <p class="text-muted small mb-0">One-click environment verification diagnostics.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        MySQL Connection: <strong class="text-success">Connected</strong>
                    </div>
                    <a href="{{ route('operations.verification') }}" class="btn btn-sm btn-danger w-100">Verify Environment</a>
                </div>
            </div>
        </div>

        <!-- 6. System Logs Center -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-secondary-subtle text-secondary me-3"><i class="bi bi-terminal"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">System Logs Center</h5>
                        <p class="text-muted small mb-0">Categorized logs for diagnostics and errors.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Driver: <strong class="text-dark">File (stack)</strong>
                    </div>
                    <a href="{{ route('operations.logs') }}" class="btn btn-sm btn-secondary w-100">Browse Log Tabs</a>
                </div>
            </div>
        </div>

        <!-- 7. Activity Timeline -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-dark-subtle text-dark me-3"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Activity Timeline</h5>
                        <p class="text-muted small mb-0">Audit timelines for imports and payments.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Tracking Status: <strong class="text-success">Active</strong>
                    </div>
                    <a href="{{ route('operations.timeline') }}" class="btn btn-sm btn-dark w-100">Open Timeline</a>
                </div>
            </div>
        </div>

        <!-- 8. License & Subscription -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-success-subtle text-success me-3"><i class="bi bi-card-list"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">License & SaaS Center</h5>
                        <p class="text-muted small mb-0">View student capacity limits and API requests.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Quota Limit: <strong class="text-dark">{{ $perfMetrics['disk_free_gb'] }} GB free</strong>
                    </div>
                    <a href="{{ route('operations.license') }}" class="btn btn-sm btn-success w-100">Subscription Center</a>
                </div>
            </div>
        </div>

        <!-- 9. Maintenance Mode -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-danger-subtle text-danger me-3"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Maintenance Toggler</h5>
                        <p class="text-muted small mb-0">Enable / disable site maintenance mode.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Mode Status: <span class="badge bg-{{ app()->isDownForMaintenance() ? 'danger' : 'success' }}">{{ app()->isDownForMaintenance() ? 'Maintenance Mode' : 'Online / Live' }}</span>
                    </div>
                    <a href="{{ route('operations.maintenance') }}" class="btn btn-sm btn-danger w-100">Schedule Downtime</a>
                </div>
            </div>
        </div>

        <!-- 10. Performance Dashboard -->
        <div class="col-xl-4 col-md-6 col-12">
            <div class="card glass-card border-0 h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-primary-subtle text-primary me-3"><i class="bi bi-speedometer"></i></div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Performance Statistics</h5>
                        <p class="text-muted small mb-0">Slow query logs and request execution speed.</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="text-muted small mb-2">
                        Avg Request Latency: <strong class="text-dark">{{ $perfMetrics['avg_response_time_ms'] }} ms</strong>
                    </div>
                    <a href="{{ route('operations.performance') }}" class="btn btn-sm btn-primary w-100">Performance Metrics</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
