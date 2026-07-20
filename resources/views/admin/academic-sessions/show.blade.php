@extends('layouts.admin')

@section('title', 'Academic Session Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Academic Session Details</h4>
                    <div class="card-tools">
                        <a href="{{ route('admin.academic-sessions.index') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <a href="{{ route('admin.academic-sessions.edit', $academicSession->id) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Name:</label>
                                <p class="form-control-static">{{ $academicSession->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Code:</label>
                                <p class="form-control-static">{{ $academicSession->code }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Start Date:</label>
                                <p class="form-control-static">{{ $academicSession->start_date->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date:</label>
                                <p class="form-control-static">{{ $academicSession->end_date->format('d M Y') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Current Session:</label>
                                <p class="form-control-static">
                                    <span class="badge badge-{{ $academicSession->is_current ? 'success' : 'secondary' }}">
                                        {{ $academicSession->is_current ? 'Yes' : 'No' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Status:</label>
                                <p class="form-control-static">
                                    <span class="badge badge-{{ $academicSession->is_active ? 'success' : 'danger' }}">
                                        {{ $academicSession->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold">Description:</label>
                                <p class="form-control-static">{{ $academicSession->description ?: 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Created At:</label>
                                <p class="form-control-static">{{ $academicSession->created_at->format('d M Y h:i A') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Updated At:</label>
                                <p class="form-control-static">{{ $academicSession->updated_at->format('d M Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    @if($academicSession->trashed())
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <h5><i class="icon fas fa-exclamation-triangle"></i> Soft Deleted!</h5>
                                <p>This academic session has been soft deleted on {{ $academicSession->deleted_at->format('d M Y h:i A') }}.</p>
                                <form method="POST" action="{{ route('admin.academic-sessions.restore', $academicSession->id) }}" class="d-inline">
                                    @csrf
                                    @method('POST')
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to restore this academic session?')">
                                        <i class="fas fa-trash-restore"></i> Restore
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.academic-sessions.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        
                        <div>
                            <a href="{{ route('admin.academic-sessions.edit', $academicSession->id) }}" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            
                            <form method="POST" action="{{ route('admin.academic-sessions.destroy', $academicSession->id) }}" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger ml-2" onclick="return confirm('Are you sure you want to delete this academic session?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection