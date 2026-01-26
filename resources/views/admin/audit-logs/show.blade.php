@extends('layouts.app')

@section('title', 'Audit Log Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-history"></i> Audit Log Details</h4>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td>{{ $log->id }}</td>
                                </tr>
                                <tr>
                                    <th>User Type:</th>
                                    <td>{{ ucfirst($log->user_type) }}</td>
                                </tr>
                                <tr>
                                    <th>User ID:</th>
                                    <td>{{ $log->user_id }}</td>
                                </tr>
                                <tr>
                                    <th>Model Type:</th>
                                    <td>{{ ucfirst($log->model_type) }}</td>
                                </tr>
                                <tr>
                                    <th>Model ID:</th>
                                    <td>{{ $log->model_id }}</td>
                                </tr>
                                <tr>
                                    <th>Field Name:</th>
                                    <td>{{ $log->field_name ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Action:</th>
                                    <td>
                                        <span class="badge 
                                            @if($log->action === 'create') bg-success
                                            @elseif($log->action === 'update') bg-warning
                                            @elseif($log->action === 'delete') bg-danger
                                            @else bg-secondary @endif">
                                            {{ $log->getReadableAction() }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Performed At:</th>
                                    <td>{{ $log->performed_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>IP Address:</th>
                                    <td>{{ $log->ip_address ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h5>Change Details</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Old Value:</h6>
                                    <pre class="bg-white p-2 rounded border">{{ $log->old_value ?: 'N/A' }}</pre>
                                    
                                    <h6 class="mt-3">New Value:</h6>
                                    <pre class="bg-white p-2 rounded border">{{ $log->new_value ?: 'N/A' }}</pre>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <h5><i class="fas fa-info-circle"></i> Log Information</h5>
                                <p>This log entry shows that a <strong>{{ $log->getReadableAction() }}</strong> action was performed on a <strong>{{ ucfirst($log->model_type) }}</strong> record (ID: {{ $log->model_id }}).</p>
                                
                                @if($log->field_name)
                                    <p>The field <strong>{{ $log->field_name }}</strong> was changed from "{{ $log->old_value ?: 'NULL' }}" to "{{ $log->new_value ?: 'NULL' }}".</p>
                                @else
                                    <p>This action affected the entire record.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <form action="{{ route('admin.audit-logs.destroy', $log) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this audit log entry?')">
                                <i class="fas fa-trash"></i> Delete Log Entry
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection