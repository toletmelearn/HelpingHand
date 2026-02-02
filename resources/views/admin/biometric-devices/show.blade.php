@extends('layouts.admin')

@section('title', 'View Biometric Device')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-hdd-network"></i> View Biometric Device
            </h1>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Biometric Device Details</h5>
                        <a href="{{ route('admin.biometric-devices.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Devices
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Device Name</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->name }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">IP Address</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->ip_address }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Port</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->port ?? '4370' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Device Type</label>
                                <p class="form-control-plaintext">
                                    @switch($biometricDevice->device_type)
                                        @case('zkteco')
                                            ZKTeco
                                            @break
                                        @case('essl')
                                            ESSL
                                            @break
                                        @case('mantra')
                                            Mantra
                                            @break
                                        @case('generic_rest')
                                            Generic REST API
                                            @break
                                        @default
                                            {{ $biometricDevice->device_type }}
                                    @endswitch
                                </p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Location</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->location ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge 
                                        @if($biometricDevice->status === 'active') bg-success 
                                        @elseif($biometricDevice->status === 'inactive') bg-danger 
                                        @elseif($biometricDevice->status === 'maintenance') bg-warning 
                                        @else bg-secondary @endif">
                                        {{ ucfirst($biometricDevice->status) }}
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Timeout</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->timeout ?? 30 }} seconds</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <p class="form-control-plaintext">{{ $biometricDevice->description ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.biometric-devices.edit', $biometricDevice) }}" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="{{ route('admin.biometric-devices.test-connection', $biometricDevice) }}" class="btn btn-info">
                            <i class="bi bi-plug"></i> Test Connection
                        </a>
                        <a href="{{ route('admin.biometric-devices.sync', $biometricDevice) }}" class="btn btn-success">
                            <i class="bi bi-arrow-repeat"></i> Sync Data
                        </a>
                        <a href="{{ route('admin.biometric-devices.logs', $biometricDevice) }}" class="btn btn-secondary">
                            <i class="bi bi-card-text"></i> View Sync Logs
                        </a>
                        <form action="{{ route('admin.biometric-devices.destroy', $biometricDevice) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this device?')">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
