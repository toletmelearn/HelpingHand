@extends('layouts.admin')

@section('title', 'Biometric Device Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-hdd-network"></i> Biometric Device Management
            </h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Devices</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-hdd-network fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Online Devices</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->where('status', 'online')->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-wifi fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Offline Devices</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->where('status', 'offline')->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-wifi-off fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Devices</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->where('is_active', true)->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-power fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Sync Errors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->where('status', 'error')->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Sync Frequency</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $devices->avg('sync_frequency') ?? 0 }} min</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
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
                <a href="{{ route('admin.biometric-devices.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Device
                </a>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.sync-monitor.sync-all') }}"><i class="bi bi-arrow-repeat"></i> Sync All Devices</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-download"></i> Export Device List</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-trash"></i> Bulk Delete</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Devices Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Device List</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                            <div class="dropdown-header">Device Options:</div>
                            <a class="dropdown-item" href="#">Refresh List</a>
                            <a class="dropdown-item" href="#">Export to CSV</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#">Delete Selected</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Connection</th>
                                    <th>Last Sync</th>
                                    <th>Sync Freq</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($devices as $device)
                                <tr>
                                    <td>
                                        <strong>{{ $device->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $device->display_name }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $device->device_type }}</span>
                                        <br>
                                        <small class="text-muted">{{ $device->connection_type }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $device->status === 'online' ? 'bg-success' : ($device->status === 'error' ? 'bg-danger' : 'bg-warning') }}">
                                            {{ ucfirst($device->status) }}
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            @if($device->is_active)
                                                <i class="bi bi-check-circle text-success"></i> Active
                                            @else
                                                <i class="bi bi-x-circle text-danger"></i> Inactive
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <code>{{ $device->connection_string }}</code>
                                    </td>
                                    <td>
                                        @if($device->last_sync_at)
                                            <small>{{ $device->last_sync_at->format('M j, Y H:i') }}</small>
                                            <br>
                                            <small class="text-muted">{{ $device->last_sync_at->diffForHumans() }}</small>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $device->sync_frequency }} min</span>
                                        <br>
                                        @if($device->real_time_sync)
                                            <span class="badge bg-success"><i class="bi bi-bolt"></i> Real-time</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.biometric-devices.edit', $device) }}" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ route('admin.biometric-devices.logs', $device) }}" class="btn btn-outline-info" title="Sync Logs">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                            <a href="{{ route('admin.biometric-devices.test-connection', $device) }}" class="btn btn-outline-success" title="Test Connection">
                                                <i class="bi bi-wifi"></i>
                                            </a>
                                            <a href="{{ route('admin.biometric-devices.sync', $device) }}" class="btn btn-outline-warning" title="Sync Now">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>
                                            <form action="{{ route('admin.biometric-devices.destroy', $device) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this device?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-hdd-network fs-1 mb-3"></i>
                                        <h4>No biometric devices configured</h4>
                                        <p class="text-muted">Get started by adding your first biometric device</p>
                                        <a href="{{ route('admin.biometric-devices.create') }}" class="btn btn-primary">
                                            <i class="bi bi-plus-circle"></i> Add Device
                                        </a>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
