@extends('layouts.admin')

@section('title', 'Notification Setting Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-bell"></i> Notification Setting Details
        </h1>
        <div>
            <a href="{{ route('admin.notification-settings.index') }}" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Settings
            </a>
            <a href="{{ route('admin.notification-settings.edit', $notificationSetting) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit Setting
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Setting Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Event Type</label>
                                <p class="form-control-plaintext">
                                    {{ App\Models\NotificationSetting::EVENT_TYPES[$notificationSetting->event_type] ?? $notificationSetting->event_type }}
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Notification Type</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-primary">
                                        {{ App\Models\NotificationSetting::NOTIFICATION_TYPES[$notificationSetting->notification_type] ?? $notificationSetting->notification_type }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Schedule Type</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info">{{ $notificationSetting->schedule_type }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    @if($notificationSetting->is_enabled)
                                        <span class="badge bg-success">Enabled</span>
                                    @else
                                        <span class="badge bg-secondary">Disabled</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created By</label>
                                <p class="form-control-plaintext">
                                    {{ $notificationSetting->creator->name ?? 'Unknown User' }}
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created At</label>
                                <p class="form-control-plaintext">
                                    {{ $notificationSetting->created_at->format('M d, Y H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-text"></i> Template Details</h5>
                </div>
                <div class="card-body">
                    @if($notificationSetting->template_subject)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Subject Template</label>
                            <div class="bg-light p-3 rounded">
                                {{ $notificationSetting->template_subject }}
                            </div>
                        </div>
                    @endif
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Template</label>
                        <div class="bg-light p-3 rounded">
                            <pre class="mb-0">{{ $notificationSetting->template_body }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipients -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Recipients</h5>
                </div>
                <div class="card-body">
                    @if($notificationSetting->recipients)
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($notificationSetting->recipients as $recipient)
                                <span class="badge bg-secondary">
                                    {{ App\Models\NotificationLog::RECIPIENT_TYPES[$recipient] ?? $recipient }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">No recipients specified</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="bg-primary text-white rounded p-3">
                                <h3 class="mb-0">{{ $logs->total() }}</h3>
                                <small>Total Notifications</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="bg-success text-white rounded p-3">
                                <h3 class="mb-0">{{ $logs->where('status', 'sent')->count() }}</h3>
                                <small>Sent</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-warning text-dark rounded p-3">
                                <h3 class="mb-0">{{ $logs->where('status', 'pending')->count() }}</h3>
                                <small>Pending</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-danger text-white rounded p-3">
                                <h3 class="mb-0">{{ $logs->where('status', 'failed')->count() }}</h3>
                                <small>Failed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sendTestModal">
                            <i class="bi bi-send"></i> Send Test Notification
                        </button>
                        <a href="{{ route('admin.notification-settings.logs') }}" class="btn btn-outline-info">
                            <i class="bi bi-list"></i> View All Logs
                        </a>
                        <form method="POST" action="{{ route('admin.notification-settings.destroy', $notificationSetting) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Are you sure you want to delete this notification setting?')">
                                <i class="bi bi-trash"></i> Delete Setting
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Logs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list"></i> Recent Notification Logs</h5>
            <a href="{{ route('admin.notification-settings.logs') }}" class="btn btn-sm btn-outline-primary">
                View All Logs
            </a>
        </div>
        <div class="card-body">
            @if($logs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>
                                        <strong>{{ App\Models\NotificationLog::RECIPIENT_TYPES[$log->recipient_type] ?? $log->recipient_type }}</strong>
                                        @if($log->recipient_id)
                                            <div class="small text-muted">ID: {{ $log->recipient_id }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ strtoupper($log->notification_type) }}</span>
                                    </td>
                                    <td>
                                        @if($log->subject)
                                            {{ Str::limit($log->subject, 30) }}
                                        @else
                                            <span class="text-muted">No subject</span>
                                        @endif
                                    </td>
                                    <td>
                                        @switch($log->status)
                                            @case('sent')
                                                <span class="badge bg-success">Sent</span>
                                                @break
                                            @case('pending')
                                                <span class="badge bg-warning text-dark">Pending</span>
                                                @break
                                            @case('failed')
                                                <span class="badge bg-danger">Failed</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($log->sent_at)
                                            {{ $log->sent_at->format('M d, Y H:i') }}
                                        @else
                                            <span class="text-muted">Not sent</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">No notification logs found for this setting.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Test Notification Modal -->
<div class="modal fade" id="sendTestModal" tabindex="-1" aria-labelledby="sendTestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendTestModalLabel">Send Test Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.notification-settings.test', $notificationSetting) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">Recipient Type</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                            <option value="">Select recipient type</option>
                            @foreach(App\Models\NotificationLog::RECIPIENT_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will send a test notification using the current template settings.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection