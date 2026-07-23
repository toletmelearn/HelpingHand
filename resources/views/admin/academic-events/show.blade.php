@extends('layouts.admin')

@section('title', 'Calendar Entry Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Calendar Entry: {{ $academicEvent->title }}</h1>
        <a href="{{ route('admin.academic-events.index') }}" class="btn btn-secondary">Back to Calendar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <p class="form-control-plaintext">{{ ucfirst($academicEvent->type) }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Academic Session</label>
                        <p class="form-control-plaintext">{{ $academicEvent->academicSession->name ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <p class="form-control-plaintext">{{ $academicEvent->start_date->format('d-m-Y') }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <p class="form-control-plaintext">{{ $academicEvent->end_date->format('d-m-Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <p class="form-control-plaintext">{{ $academicEvent->description ?? 'No description' }}</p>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <p class="form-control-plaintext">
                    @if($academicEvent->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-danger">Inactive</span>
                    @endif
                </p>
            </div>

            <div class="mb-3">
                <label class="form-label">Created At</label>
                <p class="form-control-plaintext">{{ $academicEvent->created_at->format('d-m-Y H:i:s') }}</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('admin.academic-events.edit', $academicEvent) }}" class="btn btn-warning">Edit</a>
                <a href="{{ route('admin.academic-events.index') }}" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>
@endsection
