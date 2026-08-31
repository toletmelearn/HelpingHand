@extends('layouts.teacher')

@section('title', 'Exam Datesheet - Teacher Panel')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-alt"></i> Exam Datesheet</h2>
            <p class="text-muted">Published exam schedule for your assigned classes/sections.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr><th>Date</th><th>Day</th><th>Class</th><th>Section</th><th>Subject</th><th>Time</th><th>Room</th></tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td>{{ $entry->exam_date->format('d M Y') }}</td>
                                <td>{{ $entry->day_of_week }}</td>
                                <td>{{ $entry->schoolClass->name ?? '?' }}</td>
                                <td>{{ $entry->section->name ?? 'Whole class' }}</td>
                                <td>{{ $entry->subject->name ?? '?' }}</td>
                                <td>{{ $entry->start_time }} - {{ $entry->end_time }}</td>
                                <td>{{ $entry->room ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No published exam schedule yet for your classes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
