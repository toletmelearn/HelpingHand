@extends('layouts.student')

@section('title', "Today's Periods")

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0">Today's Periods -- {{ $date->format('l, d M Y') }}</h4>
                    <a href="{{ route('student.timetable.weekly') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar-week"></i> Weekly View
                    </a>
                </div>
                <div class="card-body">
                    @if(!$student)
                        <p class="text-muted">No student record linked to this account.</p>
                    @elseif($isHoliday)
                        <p class="text-muted mb-0"><i class="fas fa-umbrella-beach"></i> Today is a school holiday -- no periods scheduled.</p>
                    @elseif($periods->isEmpty())
                        <p class="text-muted">No periods scheduled for {{ $student->name }} today.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $period)
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
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
