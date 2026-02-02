@extends('layouts.admin')

@section('title', 'Biometric Device Sync Logs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-card-text"></i> Biometric Device Sync Logs
            </h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Sync Logs for {{ $device->name }}</h5>
                        <a href="{{ route('admin.biometric-devices.show', $device) }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Device
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Timestamp</th>
                                    <th>Status</th>
                                    <th>Records Fetched</th>
                                    <th>Records Processed</th>
                                    <th>Error Message</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ $log->created_at->format('d-m-Y H:i:s') }}</td>
                                        <td>
                                            <span class="badge 
                                                @if($log->status === 'success') bg-success 
                                                @elseif($log->status === 'partial_success') bg-warning 
                                                @else bg-danger @endif">
                                                {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ $log->records_fetched ?? 0 }}</td>
                                        <td>{{ $log->records_processed ?? 0 }}</td>
                                        <td>{{ $log->error_message ?? 'N/A' }}</td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-info">View Details</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No sync logs found for this device.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
