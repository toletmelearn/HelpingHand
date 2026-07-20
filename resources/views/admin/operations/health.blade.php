@extends('layouts.admin')

@section('title', 'System Health & Diagnostics')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }
    .glass-header {
        background: linear-gradient(135deg, #1e3a8a, #0f172a);
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-weight: bold;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumbs -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Operations Control</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-heart-pulse-fill text-danger me-2"></i> System Health & Diagnostics</h3>
            <p class="text-muted">Monitor environment readiness diagnostics, database storage footprints, and service permission levels.</p>
        </div>
    </div>

    <!-- Health Overview Cards -->
    <div class="row g-3 mb-4">
        <!-- Disk Usage Card -->
        <div class="col-md-4 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Disk Space Usage</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['disk_used_percent'] }}%</h3>
                        <small class="text-muted">{{ $metrics['disk_free_gb'] }} GB free of {{ $metrics['disk_total_gb'] }} GB</small>
                    </div>
                    <div style="width: 60px;">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $metrics['disk_used_percent'] }}%" aria-valuenow="{{ $metrics['disk_used_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database footprint size -->
        <div class="col-md-4 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Database Footprint</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">{{ $metrics['db_size'] }}</h3>
                        <small class="text-muted">Engine: {{ config('database.default') }}</small>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-3" style="font-size: 1.5rem;"><i class="bi bi-database-check"></i></div>
                </div>
            </div>
        </div>

        <!-- Infrastructure status -->
        <div class="col-md-4 col-12">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Diagnostic Status</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        @php
                            $hasErrors = collect($results)->contains(fn($r) => $r['result']['status'] === 'error');
                            $hasWarnings = collect($results)->contains(fn($r) => $r['result']['status'] === 'warning');
                        @endphp
                        @if($hasErrors)
                            <h3 class="fw-bold mb-0 text-danger">Critically Failed</h3>
                            <small class="text-danger-emphasis">Resolve directory permissions or DB issues.</small>
                        @elseif($hasWarnings)
                            <h3 class="fw-bold mb-0 text-warning">Warnings Present</h3>
                            <small class="text-warning-emphasis">Queue or Scheduler idle/missing.</small>
                        @else
                            <h3 class="fw-bold mb-0 text-success">All Green</h3>
                            <small class="text-success-emphasis">Infrastructure fully launch ready.</small>
                        @endif
                    </div>
                    <div class="p-3 rounded-3 bg-{{ $hasErrors ? 'danger' : ($hasWarnings ? 'warning' : 'success') }}-subtle text-{{ $hasErrors ? 'danger' : ($hasWarnings ? 'warning' : 'success') }}" style="font-size: 1.5rem;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagnostics Check Table -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-light border-0 p-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-activity me-1 text-primary"></i> Detailed Diagnostic Report</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">Category</th>
                            <th style="width: 25%;">Diagnostic Check</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 40%;">Description & Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $item)
                            @php
                                $res = $item['result'];
                                $status = $res['status'];
                            @endphp
                            <tr>
                                <td><span class="badge bg-secondary text-uppercase">{{ $item['category'] }}</span></td>
                                <td><strong class="text-dark">{{ $item['name'] }}</strong></td>
                                <td>
                                    @if($status === 'success')
                                        <span class="status-badge bg-success text-white">SUCCESS</span>
                                    @elseif($status === 'warning')
                                        <span class="status-badge bg-warning text-dark">WARNING</span>
                                    @else
                                        <span class="status-badge bg-danger text-white">CRITICAL</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="mb-1 text-muted">{{ $res['message'] }}</div>
                                    @if(!empty($res['meta']))
                                        <div class="mt-1">
                                            @foreach($res['meta'] as $metaKey => $metaValue)
                                                <span class="badge bg-light text-dark border me-1 small">
                                                    {{ $metaKey }}: {{ is_array($metaValue) ? implode(', ', $metaValue) : $metaValue }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
