@extends('layouts.app')

@section('title', 'Special Day Override Details')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Special Day Override Details</h4>
                    <div class="card-tools">
                        <a href="{{ route('admin.special-day-overrides.index') }}" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Override Date</label>
                                <p class="form-control-plaintext">{{ $specialDayOverride->override_date->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-{{ $specialDayOverride->type == 'exam_day' ? 'danger' : ($specialDayOverride->type == 'half_day' ? 'warning' : ($specialDayOverride->type == 'event_day' ? 'info' : 'dark')) }}">
                                        {{ $specialDayOverride->getReadableType() }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Associated Schedule</label>
                                <p class="form-control-plaintext">
                                    @if($specialDayOverride->bell_schedule_id && $specialDayOverride->bellSchedule)
                                        {{ $specialDayOverride->bellSchedule->name }}
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Created By</label>
                                <p class="form-control-plaintext">{{ $specialDayOverride->createdBy->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <p class="form-control-plaintext">{{ $specialDayOverride->remarks ?: 'No remarks' }}</p>
                    </div>
                    
                    @if($specialDayOverride->custom_periods)
                    <div class="mb-4">
                        <label class="form-label">Custom Periods</label>
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
                                    @foreach($specialDayOverride->custom_periods as $period)
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
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    
                    <div class="d-flex justify-content-between">
                        @can('update', $specialDayOverride)
                        <a href="{{ route('admin.special-day-overrides.edit', $specialDayOverride) }}" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        @endcan
                        @can('delete', $specialDayOverride)
                        <form action="{{ route('admin.special-day-overrides.destroy', $specialDayOverride) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this override?')">
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