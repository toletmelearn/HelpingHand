@extends('layouts.app')

@section('title', 'Bell Schedule Details')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Bell Schedule Details</h4>
                    <div class="card-tools">
                        <a href="{{ route('admin.bell-schedules.index') }}" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <p class="form-control-plaintext">{{ $bellSchedule->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Season Type</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-{{ $bellSchedule->season_type == 'summer' ? 'warning' : 'info' }}">
                                        {{ ucfirst($bellSchedule->season_type) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-{{ $bellSchedule->isActive() ? 'success' : 'secondary' }}">
                                        {{ ucfirst($bellSchedule->status) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date Range</label>
                                <p class="form-control-plaintext">
                                    {{ $bellSchedule->start_date->format('d M Y') }} to {{ $bellSchedule->end_date->format('d M Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Target Group</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary">
                                        {{ $bellSchedule->target_group ?: 'All Classes' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Created By</label>
                        <p class="form-control-plaintext">{{ $bellSchedule->createdBy->name ?? 'N/A' }}</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Periods</label>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Period #</th>
                                        <th>Period Name</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($bellSchedule->periods)
                                        @foreach($bellSchedule->periods as $period)
                                        <tr>
                                            <td>{{ $period['period_number'] ?? 'N/A' }}</td>
                                            <td>{{ $period['period_name'] ?? 'N/A' }}</td>
                                            <td>{{ $period['start_time'] ?? 'N/A' }}</td>
                                            <td>{{ $period['end_time'] ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $period['type'] == 'teaching_period' ? 'primary' : ($period['type'] == 'short_break' ? 'warning' : ($period['type'] == 'lunch_break' ? 'info' : 'secondary')) }}">
                                                    {{ ucfirst(str_replace('_', ' ', $period['type'] ?? 'N/A')) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center">No periods defined</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        @can('update', $bellSchedule)
                        <a href="{{ route('admin.bell-schedules.edit', $bellSchedule) }}" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        @endcan
                        @can('delete', $bellSchedule)
                        <form action="{{ route('admin.bell-schedules.destroy', $bellSchedule) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this bell schedule?')">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection