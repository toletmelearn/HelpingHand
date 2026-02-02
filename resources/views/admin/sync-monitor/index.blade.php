@extends('layouts.admin')

@section('title', 'Live Sync Monitor')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-wifi"></i> Live Sync Monitor
            </h1>
        </div>
    </div>

    <!-- Sync Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Syncs (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['total_syncs'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-arrow-repeat fa-2x text-gray-300"></i>
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
                                Successful Syncs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['successful_syncs'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Failed Syncs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['failed_syncs'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
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
                                Success Rate</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['success_rate'] ?? 0 }}%</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-percent fa-2x text-gray-300"></i>
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
                <div>
                    <button type="button" class="btn btn-success" onclick="syncAllDevices()">
                        <i class="bi bi-arrow-repeat"></i> Sync All Devices Now
                    </button>
                    <button type="button" class="btn btn-primary" onclick="refreshStats()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Stats
                    </button>
                </div>
                
                <div>
                    <select class="form-select" id="timeRange" onchange="changeTimeRange()">
                        <option value="1">Last 1 Day</option>
                        <option value="7" selected>Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Sync Activity</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="syncActivityTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Started At</th>
                                    <th>Completed At</th>
                                    <th>Status</th>
                                    <th>Records Processed</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Device 1</strong>
                                        <br>
                                        <small class="text-muted">ZKTeco Fingerprint Scanner</small>
                                    </td>
                                    <td>Jan 27, 2026 10:30:15</td>
                                    <td>Jan 27, 2026 10:32:22</td>
                                    <td>
                                        <span class="badge bg-success">Success</span>
                                        <br>
                                        <small class="text-muted">2 min 7 sec</small>
                                    </td>
                                    <td>
                                        <div>Processed: 150</div>
                                        <div class="text-success">Created: 120</div>
                                        <div class="text-info">Updated: 15</div>
                                        <div class="text-warning">Duplicates: 10</div>
                                        <div class="text-danger">Errors: 5</div>
                                    </td>
                                    <td>2 min 7 sec</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-primary" title="Retry">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Device 2</strong>
                                        <br>
                                        <small class="text-muted">ESSL Biometric Terminal</small>
                                    </td>
                                    <td>Jan 27, 2026 09:45:30</td>
                                    <td>Jan 27, 2026 09:48:15</td>
                                    <td>
                                        <span class="badge bg-warning">Partial</span>
                                        <br>
                                        <small class="text-muted">2 min 45 sec</small>
                                    </td>
                                    <td>
                                        <div>Processed: 200</div>
                                        <div class="text-success">Created: 180</div>
                                        <div class="text-info">Updated: 10</div>
                                        <div class="text-warning">Duplicates: 5</div>
                                        <div class="text-danger">Errors: 5</div>
                                    </td>
                                    <td>2 min 45 sec</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="#" class="btn btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-primary" title="Retry">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>
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

    <!-- Device Status Panel -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Device Status Overview</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-success h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Online Devices</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-wifi fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-warning h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Offline Devices</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">1</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-wifi-off fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-danger h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Error Devices</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncAllDevices() {
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Syncing...';
    btn.disabled = true;
    
    // Make API call
    fetch('{{ route("admin.sync-monitor.sync-all") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ force: true })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success state
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Sync Started';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        } else {
            // Show error state
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Error';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function refreshStats() {
    // Reload the page to refresh stats
    location.reload();
}

function changeTimeRange() {
    // In a real implementation, this would reload stats for selected time range
    const timeRange = document.getElementById('timeRange').value;
    console.log('Time range changed to: ' + timeRange + ' days');
}
</script>
@endsection
