@extends('layouts.admin')

@section('title', 'Biometric Reports & Analytics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-bar-chart"></i> Biometric Reports & Analytics
            </h1>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Generate Reports</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.teacher-biometrics.reports') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Report Type</label>
                                    <select name="type" id="type" class="form-select">
                                        <option value="daily_summary" {{ request('type') == 'daily_summary' ? 'selected' : '' }}>Daily Summary</option>
                                        <option value="monthly_report" {{ request('type') == 'monthly_report' ? 'selected' : '' }}>Monthly Report</option>
                                        <option value="late_arrival_report" {{ request('type') == 'late_arrival_report' ? 'selected' : '' }}>Late Arrival Report</option>
                                        <option value="early_departure_report" {{ request('type') == 'early_departure_report' ? 'selected' : '' }}>Early Departure Report</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">Teacher (Optional)</label>
                                    <select name="teacher_id" id="teacher_id" class="form-select">
                                        <option value="">All Teachers</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" 
                                           value="{{ request('start_date', $startDate) }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control" 
                                           value="{{ request('end_date', $endDate) }}">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Generate Report
                            </button>
                            <a href="{{ route('admin.teacher-biometrics.reports', ['type' => request('type'), 'format' => 'pdf', 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'teacher_id' => request('teacher_id')]) }}" 
                               class="btn btn-danger">
                                <i class="bi bi-file-pdf"></i> Export PDF
                            </a>
                            <a href="{{ route('admin.teacher-biometrics.reports', ['type' => request('type'), 'format' => 'excel', 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'teacher_id' => request('teacher_id')]) }}" 
                               class="btn btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        @switch(request('type'))
                            @case('daily_summary')
                                Daily Summary Report
                                @break
                            @case('monthly_report')
                                Monthly Report
                                @break
                            @case('late_arrival_report')
                                Late Arrival Report
                                @break
                            @case('early_departure_report')
                                Early Departure Report
                                @break
                            @default
                                Biometric Report
                        @endswitch
                    </h5>
                </div>
                <div class="card-body">
                    @if(request('type') == 'daily_summary')
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Teachers</th>
                                        <th>Present Today</th>
                                        <th>Late Arrivals</th>
                                        <th>Early Departures</th>
                                        <th>Half Days</th>
                                        <th>Avg Working Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($data as $day)
                                        <tr>
                                            <td>{{ $day['date'] ?? 'N/A' }}</td>
                                            <td>{{ $day['total_teachers'] ?? 0 }}</td>
                                            <td>{{ $day['present_today'] ?? 0 }}</td>
                                            <td>{{ $day['late_arrivals'] ?? 0 }}</td>
                                            <td>{{ $day['early_departures'] ?? 0 }}</td>
                                            <td>{{ $day['half_days'] ?? 0 }}</td>
                                            <td>{{ number_format($day['avg_working_hours'] ?? 0, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif(request('type') == 'monthly_report')
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Summary Statistics</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Total Records:</th>
                                        <td>{{ $data->total_records ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <th>Unique Teachers:</th>
                                        <td>{{ $data->unique_teachers ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total Late Arrivals:</th>
                                        <td>{{ $data->total_late ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total Early Departures:</th>
                                        <td>{{ $data->total_early ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <th>Average Working Hours:</th>
                                        <td>{{ number_format($data->avg_working_hours ?? 0, 2) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        @if(request('teacher_id'))
                        <h6>Monthly Report for {{ $teachers->find(request('teacher_id'))->name ?? 'Selected Teacher' }}</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Arrival Time</th>
                                        <th>Departure Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($data as $record)
                                        <tr>
                                            <td>{{ $record->date->format('d-m-Y') ?? 'N/A' }}</td>
                                            <td>{{ $record->first_in_time ?? 'N/A' }}</td>
                                            <td>{{ $record->last_out_time ?? 'N/A' }}</td>
                                            <td>{{ number_format($record->calculated_duration, 2) }} hrs</td>
                                            <td>
                                                <span class="badge 
                                                    @if($record->arrival_status === 'on_time' && $record->departure_status === 'on_time') bg-success 
                                                    @elseif($record->arrival_status === 'late' || $record->departure_status === 'early_exit') bg-warning 
                                                    @else bg-secondary @endif">
                                                    {{ ucfirst(str_replace('_', ' ', $record->arrival_status)) }} / {{ ucfirst(str_replace('_', ' ', $record->departure_status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No records found for the selected teacher and period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @elseif(request('type') == 'late_arrival_report')
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Date</th>
                                        <th>Arrival Time</th>
                                        <th>Late Minutes</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($data as $record)
                                        <tr>
                                            <td>{{ $record->teacher->name ?? 'N/A' }}</td>
                                            <td>{{ $record->date->format('d-m-Y') }}</td>
                                            <td>{{ $record->first_in_time ?? 'N/A' }}</td>
                                            <td>{{ $record->late_minutes ?? 0 }} mins</td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    {{ ucfirst(str_replace('_', ' ', $record->arrival_status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No late arrival records found for the selected period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif(request('type') == 'early_departure_report')
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Date</th>
                                        <th>Departure Time</th>
                                        <th>Early Minutes</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($data as $record)
                                        <tr>
                                            <td>{{ $record->teacher->name ?? 'N/A' }}</td>
                                            <td>{{ $record->date->format('d-m-Y') }}</td>
                                            <td>{{ $record->last_out_time ?? 'N/A' }}</td>
                                            <td>{{ $record->early_departure_minutes ?? 0 }} mins</td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    {{ ucfirst(str_replace('_', ' ', $record->departure_status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No early departure records found for the selected period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Please select a report type to generate the report.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
