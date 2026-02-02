@extends('layouts.admin')

@section('title', 'Subject Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Subject Details: {{ $subject->name }}</h1>
        <a href="{{ route('admin.subjects.index') }}" class="btn btn-secondary">Back to Subjects</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <p class="form-control-plaintext">{{ $subject->name }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <p class="form-control-plaintext">{{ $subject->code }}</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <p class="form-control-plaintext">
                            @if($subject->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                            
                            @if($subject->trashed())
                                <span class="badge bg-danger">Deleted</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <p class="form-control-plaintext">{{ $subject->description ?? 'No description' }}</p>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Created At</label>
                <p class="form-control-plaintext">{{ $subject->created_at->format('d-m-Y H:i:s') }}</p>
            </div>
            
            @if($subject->updated_at != $subject->created_at)
            <div class="mb-3">
                <label class="form-label">Updated At</label>
                <p class="form-control-plaintext">{{ $subject->updated_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif
            
            @if($subject->trashed())
            <div class="mb-3">
                <label class="form-label">Deleted At</label>
                <p class="form-control-plaintext">{{ $subject->deleted_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif
            
            <div class="d-flex gap-2">
                <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-warning">Edit</a>
                <a href="{{ route('admin.subjects.index') }}" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>
@endsection
