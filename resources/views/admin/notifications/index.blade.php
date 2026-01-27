@extends('layouts.app')

@section('title', 'Notification Rules')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-bell"></i> Notification Rules Management
            </h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Templates</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">12</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-template fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Templates</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">10</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Channels</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Events</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">5</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-lightning-charge fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Template
                </a>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="testAllTemplates()"><i class="bi bi-send-check"></i> Test All Templates</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-download"></i> Export Templates</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-upload"></i> Import Templates</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-trash"></i> Bulk Delete</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Notification Templates</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="notificationDropdown">
                            <div class="dropdown-header">Template Options:</div>
                            <a class="dropdown-item" href="#">Refresh List</a>
                            <a class="dropdown-item" href="#">Export to CSV</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#">Delete Selected</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="notificationTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Event Type</th>
                                    <th>Channel</th>
                                    <th>Status</th>
                                    <th>Variables</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Late Arrival Alert</strong>
                                        <br>
                                        <small class="text-muted">Alert teachers about late arrivals</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Late Arrival</span>
                                        <br>
                                        <small class="text-muted">Triggers when arrival is delayed</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Email</span>
                                        <span class="badge bg-success ms-1">SMS</span>
                                        <br>
                                        <small class="text-muted">Push: Yes</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                        <br>
                                        <small class="text-muted">Delay: 0 min</small>
                                    </td>
                                    <td>
                                        <small>
                                            <span class="badge bg-light text-dark">teacher_name</span>
                                            <span class="badge bg-light text-dark">date</span>
                                            <span class="badge bg-light text-dark">late_minutes</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.notifications.edit', 1) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-success" title="Test Template" onclick="testTemplate(1)">
                                                <i class="bi bi-send-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form action="{{ route('admin.notifications.destroy', 1) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this template?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Early Departure Warning</strong>
                                        <br>
                                        <small class="text-muted">Notify about early departures</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">Early Departure</span>
                                        <br>
                                        <small class="text-muted">Triggers when leaving early</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Email</span>
                                        <span class="badge bg-success ms-1">SMS</span>
                                        <br>
                                        <small class="text-muted">Push: Yes</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                        <br>
                                        <small class="text-muted">Delay: 0 min</small>
                                    </td>
                                    <td>
                                        <small>
                                            <span class="badge bg-light text-dark">teacher_name</span>
                                            <span class="badge bg-light text-dark">date</span>
                                            <span class="badge bg-light text-dark">early_minutes</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.notifications.edit', 2) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-success" title="Test Template" onclick="testTemplate(2)">
                                                <i class="bi bi-send-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form action="{{ route('admin.notifications.destroy', 2) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this template?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Monthly Summary</strong>
                                        <br>
                                        <small class="text-muted">Monthly attendance summary</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">Monthly Summary</span>
                                        <br>
                                        <small class="text-muted">Triggers at month end</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Email</span>
                                        <br>
                                        <small class="text-muted">Push: Yes</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                        <br>
                                        <small class="text-muted">Delay: 0 min</small>
                                    </td>
                                    <td>
                                        <small>
                                            <span class="badge bg-light text-dark">teacher_name</span>
                                            <span class="badge bg-light text-dark">month</span>
                                            <span class="badge bg-light text-dark">attendance_rate</span>
                                            <span class="badge bg-light text-dark">working_hours</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.notifications.edit', 3) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-success" title="Test Template" onclick="testTemplate(3)">
                                                <i class="bi bi-send-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form action="{{ route('admin.notifications.destroy', 3) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this template?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Performance Alert</strong>
                                        <br>
                                        <small class="text-muted">Performance score notifications</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">Performance Alert</span>
                                        <br>
                                        <small class="text-muted">Triggers on low performance</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Email</span>
                                        <span class="badge bg-success ms-1">SMS</span>
                                        <br>
                                        <small class="text-muted">Push: Yes</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                        <br>
                                        <small class="text-muted">Delay: 0 min</small>
                                    </td>
                                    <td>
                                        <small>
                                            <span class="badge bg-light text-dark">teacher_name</span>
                                            <span class="badge bg-light text-dark">score</span>
                                            <span class="badge bg-light text-dark">grade</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.notifications.edit', 4) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-success" title="Test Template" onclick="testTemplate(4)">
                                                <i class="bi bi-send-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" title="Preview">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form action="{{ route('admin.notifications.destroy', 4) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this template?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Panel -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Notification Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-primary">Global Settings</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="enableNotifications" checked>
                                <label class="form-check-label" for="enableNotifications">
                                    Enable All Notifications
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="sendAdminCopy" checked>
                                <label class="form-check-label" for="sendAdminCopy">
                                    Send Admin Copy of All Notifications
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="requireApproval">
                                <label class="form-check-label" for="requireApproval">
                                    Require Approval for Critical Notifications
                                </label>
                            </div>
                            
                            <div class="mt-3">
                                <label for="defaultDelay" class="form-label">Default Delay (minutes)</label>
                                <input type="number" class="form-control" id="defaultDelay" value="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-primary">Channels Configuration</h6>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Email Settings</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="emailEnabled" checked>
                                        <label class="form-check-label" for="emailEnabled">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                    <div class="mb-2">
                                        <label for="emailFrom" class="form-label">From Address</label>
                                        <input type="email" class="form-control" id="emailFrom" value="noreply@helpinghand.edu">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">SMS Settings</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="smsEnabled" checked>
                                        <label class="form-check-label" for="smsEnabled">
                                            Enable SMS Notifications
                                        </label>
                                    </div>
                                    <div class="mb-2">
                                        <label for="smsProvider" class="form-label">Provider</label>
                                        <select class="form-select" id="smsProvider">
                                            <option>Twilio</option>
                                            <option>SMS Gateway</option>
                                            <option>Custom Provider</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="saveConfiguration()">Save Configuration</button>
                        <button class="btn btn-secondary" onclick="resetConfiguration()">Reset to Defaults</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testTemplate(templateId) {
    // Show loading state
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Show success state
        btn.innerHTML = '<i class="bi bi-check-circle text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }, 2000);
    }, 1500);
}

function testAllTemplates() {
    // Show loading state
    const btn = event.target;
    const originalText = btn.textContent;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Show success state
        btn.innerHTML = '<i class="bi bi-check-circle"></i> All Tested';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 3000);
    }, 2000);
}

function saveConfiguration() {
    // Simulate saving configuration
    alert('Configuration saved successfully!');
}

function resetConfiguration() {
    // Simulate resetting configuration
    if (confirm('Are you sure you want to reset all notification settings to defaults?')) {
        alert('Configuration reset to defaults.');
    }
}
</script>
@endsection