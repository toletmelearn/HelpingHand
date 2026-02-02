@extends('layouts.admin')

@section('title', 'Edit Biometric Device')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-pencil"></i> Edit Biometric Device
            </h1>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Update Biometric Device</h5>
                        <a href="{{ route('admin.biometric-devices.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Devices
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.biometric-devices.update', $biometricDevice) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Device Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $biometricDevice->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="ip_address" class="form-label">IP Address *</label>
                                    <input type="text" name="ip_address" id="ip_address" class="form-control @error('ip_address') is-invalid @enderror" 
                                           value="{{ old('ip_address', $biometricDevice->ip_address) }}" required>
                                    @error('ip_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="port" class="form-label">Port</label>
                                    <input type="number" name="port" id="port" class="form-control @error('port') is-invalid @enderror" 
                                           value="{{ old('port', $biometricDevice->port ?? 4370) }}">
                                    @error('port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="device_type" class="form-label">Device Type</label>
                                    <select name="device_type" id="device_type" class="form-select @error('device_type') is-invalid @enderror">
                                        <option value="zkteco" {{ old('device_type', $biometricDevice->device_type) == 'zkteco' ? 'selected' : '' }}>ZKTeco</option>
                                        <option value="essl" {{ old('device_type', $biometricDevice->device_type) == 'essl' ? 'selected' : '' }}>ESSL</option>
                                        <option value="mantra" {{ old('device_type', $biometricDevice->device_type) == 'mantra' ? 'selected' : '' }}>Mantra</option>
                                        <option value="generic_rest" {{ old('device_type', $biometricDevice->device_type) == 'generic_rest' ? 'selected' : '' }}>Generic REST API</option>
                                    </select>
                                    @error('device_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" name="location" id="location" class="form-control @error('location') is-invalid @enderror" 
                                           value="{{ old('location', $biometricDevice->location) }}">
                                    @error('location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                        <option value="active" {{ old('status', $biometricDevice->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $biometricDevice->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="maintenance" {{ old('status', $biometricDevice->status) == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $biometricDevice->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="timeout" class="form-label">Timeout (seconds)</label>
                                    <input type="number" name="timeout" id="timeout" class="form-control @error('timeout') is-invalid @enderror" 
                                           value="{{ old('timeout', $biometricDevice->timeout ?? 30) }}">
                                    @error('timeout')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Device
                            </button>
                            <a href="{{ route('admin.biometric-devices.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
