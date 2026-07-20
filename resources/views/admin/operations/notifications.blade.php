@extends('layouts.admin')

@section('title', 'Notification Logs & Settings')

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
                    <li class="breadcrumb-item active" aria-current="page">Notification Center</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-chat-left-dots text-success me-2"></i> Notification Delivery Center</h3>
            <p class="text-muted">Observe delivery status channels (Email, SMS, WhatsApp, Push), resolve template variables, and retry failed transmissions.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics row -->
    <div class="row g-3 mb-4">
        <!-- Total sent -->
        <div class="col-md-3 col-6">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Total Dispatched</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="fw-bold mb-0 text-dark">{{ $stats['total'] }}</h3>
                    <div class="icon-box text-primary fs-3"><i class="bi bi-send"></i></div>
                </div>
            </div>
        </div>

        <!-- Success -->
        <div class="col-md-3 col-6">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Delivery Success</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="fw-bold mb-0 text-success">{{ $stats['sent'] }}</h3>
                    <div class="icon-box text-success fs-3"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>

        <!-- Pending -->
        <div class="col-md-3 col-6">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Pending Queued</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="fw-bold mb-0 text-warning">{{ $stats['pending'] }}</h3>
                    <div class="icon-box text-warning fs-3"><i class="bi bi-hourglass"></i></div>
                </div>
            </div>
        </div>

        <!-- Failed -->
        <div class="col-md-3 col-6">
            <div class="card glass-card border-0 p-3 h-100">
                <h6 class="text-muted small text-uppercase fw-bold mb-2">Transmission Failures</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="fw-bold mb-0 text-danger">{{ $stats['failed'] }}</h3>
                    <div class="icon-box text-danger fs-3"><i class="bi bi-exclamation-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="row g-4">
        <!-- Log Table -->
        <div class="col-lg-8 col-12">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header bg-light border-0 p-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-columns-reverse me-1 text-primary"></i> Real-time Notification Logs</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Recipient</th>
                                    <th>Channel</th>
                                    <th>Subject / Body</th>
                                    <th>Status</th>
                                    <th>Dispatched At</th>
                                    <th class="text-end">Retry</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block">
                                                {{ $log->recipient ? ($log->recipient->name ?? 'User #' . $log->recipient_id) : 'Recipient #' . $log->recipient_id }}
                                            </strong>
                                            <span class="text-muted small text-capitalize">{{ $log->recipient_type ?: 'Unknown' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-uppercase">
                                                @if($log->notification_type === 'email')
                                                    <i class="bi bi-envelope-fill text-primary me-1"></i> Email
                                                @elseif($log->notification_type === 'sms')
                                                    <i class="bi bi-phone-fill text-info me-1"></i> SMS
                                                @elseif($log->notification_type === 'whatsapp')
                                                    <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp
                                                @else
                                                    <i class="bi bi-bell-fill text-secondary me-1"></i> In-App
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block text-truncate" style="max-width: 200px;">{{ $log->subject ?: 'Message Body' }}</strong>
                                            <small class="text-muted d-block text-truncate" style="max-width: 200px;">{{ $log->message }}</small>
                                            @if($log->failed_reason)
                                                <small class="text-danger d-block">Error: {{ $log->failed_reason }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->status === 'sent')
                                                <span class="badge bg-success">SENT</span>
                                            @elseif($log->status === 'pending')
                                                <span class="badge bg-warning text-dark">PENDING</span>
                                            @else
                                                <span class="badge bg-danger">FAILED</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            @if($log->status === 'failed')
                                                <form action="{{ route('operations.notifications.retry', $log->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Retry Delivery">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-chat-left-dots fs-1 d-block mb-2"></i>
                                            No recent notification logs found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($logs->hasPages())
                        <div class="p-3 border-top">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Template and Variable Info -->
        <div class="col-lg-4 col-12">
            <div class="card glass-card border-0 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-code me-1 text-primary"></i> Notification Templates</h5>
                <p class="text-muted small">Manage text copy, layout structures, and trigger conditions for automated notifications.</p>
                
                <div class="list-group list-group-flush border-top">
                    @forelse($templates as $template)
                        <div class="list-group-item py-2 px-0 bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-dark small">{{ $template->name }}</strong>
                                <span class="badge bg-secondary-subtle text-secondary text-uppercase small">{{ $template->channel }}</span>
                            </div>
                            <small class="text-muted text-truncate d-block">{{ $template->event_type }}</small>
                        </div>
                    @empty
                        <div class="py-3 text-center text-muted small">No active templates configured.</div>
                    @endforelse
                </div>
            </div>

            <!-- Variable Mappings Reference -->
            <div class="card glass-card border-0 p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-braces me-1 text-primary"></i> Variable Reference</h5>
                <p class="text-muted small">Use double braces in templates to dynamically map data inputs:</p>
                <table class="table table-sm table-borderless mb-0 small">
                    <tbody>
                        <tr>
                            <td><code>@{{ student_name }}</code></td>
                            <td class="text-muted">Student's Full Name</td>
                        </tr>
                        <tr>
                            <td><code>@{{ parent_name }}</code></td>
                            <td class="text-muted">Parent's Full Name</td>
                        </tr>
                        <tr>
                            <td><code>@{{ fee_amount }}</code></td>
                            <td class="text-muted">Fee Amount Due</td>
                        </tr>
                        <tr>
                            <td><code>@{{ school_name }}</code></td>
                            <td class="text-muted">Registered School Name</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
