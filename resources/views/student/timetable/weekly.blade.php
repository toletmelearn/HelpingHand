@extends('layouts.student')

@section('title', 'Weekly Timetable')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Weekly Timetable @if($student) -- {{ $student->name }} @endif</h4>
        <div>
            @if($student && count($days) > 0)
                <a href="{{ route('student.timetable.download-pdf') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
            @endif
            <a href="{{ route('student.timetable.today') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-calendar-day"></i> Today's View
            </a>
        </div>
    </div>

    @if($holidays->isNotEmpty())
        <div class="alert alert-info">
            <i class="fas fa-umbrella-beach"></i> Holiday(s) this week:
            @foreach($holidays as $holiday)
                {{ $holiday->holiday_name }} ({{ $holiday->start_date->format('d M') }}–{{ $holiday->end_date->format('d M') }}){{ !$loop->last ? ',' : '' }}
            @endforeach
        </div>
    @endif

    @if(!$student)
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">No student record linked to this account.</p>
            </div>
        </div>
    @elseif(count($days) === 0)
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-calendar-times fa-2x mb-3"></i>
                <p class="mb-0">No published timetable is available for {{ $student->name }} yet.</p>
                <p class="mb-0 small">Once the school publishes the timetable, the weekly schedule will appear here.</p>
            </div>
        </div>
    @else
        @foreach($days as $day)
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">{{ $day }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($periodsByDay->get($day, collect()) as $period)
                                    <tr>
                                        <td>{{ $period->period_name }}</td>
                                        <td>
                                            @if($period->start_time && $period->end_time)
                                                {{ $period->start_time->format('h:i A') }} - {{ $period->end_time->format('h:i A') }}
                                            @endif
                                        </td>
                                        <td>{{ $period->subject_name }}</td>
                                        <td>
                                            {{ $period->teacher_name }}
                                            @if($period->is_arrangement)
                                                <span class="badge bg-warning text-dark">Arrangement</span>
                                            @endif
                                        </td>
                                        <td>{{ $period->room_number ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
