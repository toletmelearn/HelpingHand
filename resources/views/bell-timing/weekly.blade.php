@extends('layouts.app')

@section('title', 'Weekly Bell Schedule')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-calendar-week"></i> Weekly Bell Schedule</h2>
        <div>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Bell Times
            </a>
            <a href="{{ route('bell-timing.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Schedule
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Schedule</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('bell-timing.weekly') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label for="class_section" class="form-label">Class/Section</label>
                        <select class="form-select" id="class_section" name="class_section">
                            <option value="">All Classes</option>
                            @foreach($classSections as $section)
                                <option value="{{ $section }}" {{ request('class_section') == $section ? 'selected' : '' }}>
                                    {{ $section }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <select class="form-select" id="academic_year" name="academic_year">
                            <option value="">All Years</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}" {{ request('academic_year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Weekly Schedule -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-table"></i> Weekly Bell Schedule</h5>
        </div>
        <div class="card-body">
            @if($schedules->isEmpty())
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No bell schedules found for the selected criteria.
                    <a href="{{ route('bell-timing.create') }}" class="alert-link">Create a new schedule</a>.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Day</th>
                                <th>Period</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Duration</th>
                                <th>Type</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                @php
                                    $daySchedules = $schedules->where('day_of_week', $day)->sortBy('order_index');
                                @endphp
                                
                                @if($daySchedules->count() > 0)
                                    @foreach($daySchedules as $schedule)
                                        <tr>
                                            <td>
                                                @if($loop->first)
                                                    <strong>{{ $day }}</strong>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    {{ $schedule->period_name }}
                                                </span>
                                                @if($schedule->custom_label)
                                                    <br><small class="text-muted">{{ $schedule->custom_label }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $schedule->start_time->format('h:i A') }}</td>
                                            <td>{{ $schedule->end_time->format('h:i A') }}</td>
                                            <td>{{ $schedule->duration_formatted }}</td>
                                            <td>
                                                @if($schedule->is_break)
                                                    <span class="badge bg-warning">Break</span>
                                                @else
                                                    <span class="badge bg-success">Class</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($schedule->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td><strong>{{ $day }}</strong></td>
                                        <td colspan="6" class="text-muted">No schedule</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Schedule Summary -->
    @if(!$schedules->isEmpty())
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Schedule Summary</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Days Scheduled
                            <span class="badge bg-primary rounded-pill">{{ $schedules->pluck('day_of_week')->unique()->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Periods
                            <span class="badge bg-success rounded-pill">{{ $schedules->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active Schedules
                            <span class="badge bg-success rounded-pill">{{ $schedules->where('is_active', true)->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Break Periods
                            <span class="badge bg-warning rounded-pill">{{ $schedules->where('is_break', true)->count() }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Created By</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        This schedule was created by:
                        @if($schedules->first()->createdBy)
                            <strong>{{ $schedules->first()->createdBy->name }}</strong>
                        @else
                            <em>Unknown User</em>
                        @endif
                    </p>
                    <p class="card-text">
                        <small class="text-muted">
                            Created: {{ $schedules->min('created_at')->format('M j, Y g:i A') }}<br>
                            Updated: {{ $schedules->max('updated_at')->format('M j, Y g:i A') }}
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection