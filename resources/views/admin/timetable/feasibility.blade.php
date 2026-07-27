@extends('layouts.admin')

@section('title', 'Timetable Feasibility Report')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    .flag-row { background: #fdeaea !important; }
    .ok-row { background: #eafaf0 !important; }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Timetable Feasibility Report</h1>
        <a href="{{ route('timetable.index') }}" class="btn btn-outline-secondary btn-sm">Back to Timetable</a>
    </div>

    <div class="card glass-card mb-4">
        <div class="card-body">
            <form action="{{ route('timetable.feasibility') }}" method="GET" class="row align-items-end">
                <div class="col-md-6">
                    <label for="academic_session_id" class="font-weight-bold text-dark">Academic Session</label>
                    <select name="academic_session_id" id="academic_session_id" class="form-control" onchange="this.form.submit()">
                        @forelse($sessions as $session)
                            <option value="{{ $session->id }}" {{ optional($selectedSession)->id == $session->id ? 'selected' : '' }}>
                                {{ $session->name }} {{ $session->is_current ? '(current)' : '' }}
                            </option>
                        @empty
                            <option value="">No academic sessions found</option>
                        @endforelse
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if(!$selectedSession)
        <div class="alert alert-warning">No academic session selected -- create one first.</div>
    @else

    <!-- Grid Capacity -->
    <div class="card shadow mb-4">
        <div class="card-header font-weight-bold">Grid Capacity per Class-Section</div>
        <div class="card-body">
            @if(empty($report['grid_capacity']))
                <p class="text-muted mb-0">No active classes found.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Class-Section</th>
                                <th>Placed</th>
                                <th>Capacity</th>
                                <th>Empty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['grid_capacity'] as $row)
                                <tr class="{{ $row['empty'] > 0 && $row['capacity'] > 0 ? 'flag-row' : ($row['capacity'] > 0 ? 'ok-row' : '') }}">
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['placed'] }}</td>
                                    <td>{{ $row['capacity'] }}</td>
                                    <td>{{ $row['empty'] }}</td>
                                    <td>{{ $row['sentence'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Teacher Load -->
    <div class="card shadow mb-4">
        <div class="card-header font-weight-bold">Teacher Load</div>
        <div class="card-body">
            @if(empty($report['teacher_load']))
                <p class="text-muted mb-0">No active teachers found.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Placed / Week</th>
                                <th>Busiest Day</th>
                                <th>Days Fully Booked</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['teacher_load'] as $row)
                                <tr class="{{ $row['over_threshold'] ? 'flag-row' : '' }}">
                                    <td>{{ $row['teacher_name'] }}</td>
                                    <td>{{ $row['placed_periods'] }} / {{ $report['threshold'] }}</td>
                                    <td>{{ $row['busiest_day'] ?? '—' }} @if($row['busiest_day']) ({{ $row['busiest_day_count'] }}) @endif</td>
                                    <td>{{ $row['days_with_zero_free_periods'] }}</td>
                                    <td class="{{ $row['over_threshold'] ? 'text-danger font-weight-bold' : '' }}">{{ $row['sentence'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Conflict Scan -->
    <div class="card shadow mb-4">
        <div class="card-header font-weight-bold">Conflict Scan</div>
        <div class="card-body">
            @if(empty($report['conflicts']))
                <p class="text-success mb-0"><i class="fas fa-check-circle"></i> No conflicts found.</p>
            @else
                <ul class="list-group">
                    @foreach($report['conflicts'] as $conflict)
                        <li class="list-group-item list-group-item-danger">{{ $conflict['sentence'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @endif
</div>
@endsection
