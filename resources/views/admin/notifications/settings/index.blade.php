@extends('layouts.admin')

@section('title', 'Notification Settings')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-bell"></i> Notification Settings
        </h1>
        <div>
            <a href="{{ route('admin.notification-settings.logs') }}" class="btn btn-outline-info me-2">
                <i class="bi bi-list"></i> View Logs
            </a>
            <a href="{{ route('admin.notification-settings.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Setting
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="card-title">{{ $settings->total() }}</h3>
                    <p class="card-text">Total Settings</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="card-title">{{ $settings->where('is_enabled', true)->count() }}</h3>
                    <p class="card-text">Active Settings</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3 class="card-title">{{ App\Models\NotificationLog::where('status', 'pending')->count() }}</h3>
                    <p class="card-text">Pending Notifications</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="card-title">{{ App\Models\NotificationLog::where('status', 'failed')->count() }}</h3>
                    <p class="card-text">Failed Notifications</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.notification-settings.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="event_type" class="form-label">Event Type</label>
                    <select name="event_type" id="event_type" class="form-select">
                        <option value="">All Events</option>
                        @foreach($eventTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('event_type') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="notification_type" class="form-label">Notification Type</label>
                    <select name="notification_type" id="notification_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach($notificationTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('notification_type') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="{{ route('admin.notification-settings.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-gear"></i> Notification Settings</h5>
        </div>
        <div class="card-body">
            @if($settings->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Event Type</th>
                                <th>Notification Type</th>
                                <th>Recipients</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settings as $setting)
                                <tr>
                                    <td>
                                        <strong>{{ $eventTypes[$setting->event_type] ?? $setting->event_type }}</strong>
                                        @if($setting->template_subject)
                                            <div class="small text-muted">Subject: {{ Str::limit($setting->template_subject, 30) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $notificationTypes[$setting->notification_type] ?? $setting->notification_type }}</span>
                                    </td>
                                    <td>
                                        @foreach($setting->recipients as $recipient)
                                            <span class="badge bg-secondary me-1">{{ $recipientTypes[$recipient] ?? $recipient }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $setting->schedule_type }}</span>
                                    </td>
                                    <td>
                                        @if($setting->is_enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-secondary">Disabled</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $setting->creator->name ?? 'System' }}
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('admin.notification-settings.show', $setting) }}" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.notification-settings.edit', $setting) }}" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.notification-settings.destroy', $setting) }}" 
                                                  method="POST" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this notification setting?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    {{ $settings->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <h4 class="mt-3">No Notification Settings Found</h4>
                    <p class="text-muted">Create your first notification setting to automate school communications.</p>
                    <a href="{{ route('admin.notification-settings.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Setting
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-envelope fs-1 text-primary"></i>
                        <h5>Email Configuration</h5>
                        <p class="text-muted small">Setup SMTP settings for email notifications</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Configure</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-phone fs-1 text-success"></i>
                        <h5>SMS Gateway</h5>
                        <p class="text-muted small">Connect SMS service provider for text notifications</p>
                        <a href="#" class="btn btn-sm btn-outline-success">Configure</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <i class="bi bi-clock-history fs-1 text-info"></i>
                        <h5>Scheduled Notifications</h5>
                        <p class="text-muted small">Manage automated notification schedules</p>
                        <a href="{{ route('admin.notification-settings.logs') }}" class="btn btn-sm btn-outline-info">View Schedule</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
