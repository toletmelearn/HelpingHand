@extends('layouts.app')

@section('title', 'Section Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Section Details: {{ $section->name }}</h1>
        <a href="{{ route('admin.sections.index') }}" class="btn btn-secondary">Back to Sections</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <p class="form-control-plaintext">{{ $section->name }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <p class="form-control-plaintext">{{ $section->capacity ?? 'Unlimited' }}</p>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <p class="form-control-plaintext">{{ $section->description ?? 'No description' }}</p>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Status</label>
                <p class="form-control-plaintext">
                    @if($section->trashed())
                        <span class="badge bg-danger">Deleted</span>
                    @else
                        <span class="badge bg-success">Active</span>
                    @endif
                </p>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Created At</label>
                <p class="form-control-plaintext">{{ $section->created_at->format('d-m-Y H:i:s') }}</p>
            </div>
            
            @if($section->updated_at != $section->created_at)
            <div class="mb-3">
                <label class="form-label">Updated At</label>
                <p class="form-control-plaintext">{{ $section->updated_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif
            
            @if($section->trashed())
            <div class="mb-3">
                <label class="form-label">Deleted At</label>
                <p class="form-control-plaintext">{{ $section->deleted_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif
            
            <div class="d-flex gap-2">
                <a href="{{ route('admin.sections.edit', $section) }}" class="btn btn-warning">Edit</a>
                <a href="{{ route('admin.sections.index') }}" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>
@endsection