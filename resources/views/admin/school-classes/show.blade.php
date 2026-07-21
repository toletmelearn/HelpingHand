@extends('layouts.admin')

@section('title', 'Class Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Class Details: {{ $schoolClass->name }}</h1>
        <a href="{{ route('admin.school-classes.index') }}" class="btn btn-secondary">Back to Classes</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <p class="form-control-plaintext">{{ $schoolClass->name }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Class Order</label>
                        <p class="form-control-plaintext">{{ $schoolClass->class_order }}</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <p class="form-control-plaintext">{{ $schoolClass->section->name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Academic Session</label>
                        <p class="form-control-plaintext">{{ $schoolClass->academicSession->name ?? 'Not assigned' }}</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Class Teacher</label>
                        <p class="form-control-plaintext">{{ $schoolClass->teacher->name ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <p class="form-control-plaintext">
                            @if($schoolClass->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif

                            @if($schoolClass->trashed())
                                <span class="badge bg-danger">Deleted</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <p class="form-control-plaintext">{{ $schoolClass->description ?? 'No description' }}</p>
            </div>

            <div class="mb-3">
                <label class="form-label">Created At</label>
                <p class="form-control-plaintext">{{ $schoolClass->created_at->format('d-m-Y H:i:s') }}</p>
            </div>

            @if($schoolClass->updated_at != $schoolClass->created_at)
            <div class="mb-3">
                <label class="form-label">Updated At</label>
                <p class="form-control-plaintext">{{ $schoolClass->updated_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif

            @if($schoolClass->trashed())
            <div class="mb-3">
                <label class="form-label">Deleted At</label>
                <p class="form-control-plaintext">{{ $schoolClass->deleted_at->format('d-m-Y H:i:s') }}</p>
            </div>
            @endif

            <div class="d-flex gap-2">
                @if(!$schoolClass->trashed())
                    <a href="{{ route('admin.school-classes.edit', $schoolClass) }}" class="btn btn-warning">Edit</a>
                @endif
                <a href="{{ route('admin.school-classes.index') }}" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>
@endsection
