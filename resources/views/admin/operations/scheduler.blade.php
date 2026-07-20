@extends('layouts.admin')

@section('title', 'Scheduler Dashboard')

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
                    <li class="breadcrumb-item active" aria-current="page">Task Scheduler</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-calendar-check text-warning me-2"></i> Scheduler Dashboard</h3>
            <p class="text-muted">Observe automated cron frequencies, background execution schedules (Reminders, Backups), and check runner heartbeats.</p>
        </div>
    </div>

    <!-- Heartbeat alert -->
    <div class="card glass-card border-0 mb-4 p-3 bg-light-subtle">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="bg-{{ $heartbeatTime !== 'Never' ? 'success' : 'danger' }}-subtle text-{{ $heartbeatTime !== 'Never' ? 'success' : 'danger' }} p-3 rounded me-3 fs-4">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Scheduler Heartbeat Tracker</h6>
                    @if($heartbeatTime !== 'Never')
                        <p class="text-muted small mb-0">Detected scheduler heartbeat at: <strong>{{ $heartbeatTime }}</strong>.</p>
                    @else
                        <p class="text-danger small mb-0">No scheduler heartbeat detected! Ensure you have configured <code>* * * * * php artisan schedule:run</code> in system crontab.</p>
                    @endif
                </div>
            </div>
            <div>
                <span class="badge bg-{{ $heartbeatTime !== 'Never' ? 'success' : 'danger' }} text-uppercase px-3 py-2">
                    {{ $heartbeatTime !== 'Never' ? 'Active' : 'Missing' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Scheduled Tasks Grid/Table -->
    <div class="card glass-card border-0">
        <div class="card-header bg-light border-0 p-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-task me-1 text-primary"></i> Registered System Scheduled Tasks</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 25%;">Scheduled Task Command</th>
                            <th style="width: 15%;">Frequency (Cron)</th>
                            <th style="width: 40%;">Description & Functional Area</th>
                            <th style="width: 10%;">Timezone</th>
                            <th style="width: 10%;" class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            <tr>
                                <td>
                                    <code class="text-dark bg-light px-2 py-1 rounded small border">
                                        {{ $task['command'] }}
                                    </code>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border fw-bold">
                                        {{ $task['expression'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark fw-semibold mb-1">
                                        @if(str_contains($task['command'], 'reminders'))
                                            Late Fee & Alerts Engine
                                        @elseif(str_contains($task['command'], 'backup'))
                                            Disaster Recovery Backups
                                        @elseif(str_contains($task['command'], 'attendance'))
                                            Attendance Checkers
                                        @else
                                            System Helper
                                        @endif
                                    </div>
                                    <span class="text-muted small">{{ $task['description'] }}</span>
                                </td>
                                <td><span class="text-muted small">{{ $task['timezone'] }}</span></td>
                                <td class="text-end">
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        ✓ REGISTERED
                                    </span>
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
