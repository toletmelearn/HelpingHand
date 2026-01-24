@extends('layouts.app')

@section('title', 'Upcoming Exams')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Upcoming Exams</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Exam Date</th>
                                    <th>Exam Time</th>
                                    <th>Duration</th>
                                    <th>Total Marks</th>
                                    <th>Uploaded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($upcomingPapers as $paper)
                                <tr>
                                    <td>{{ $paper->title }}</td>
                                    <td>{{ $paper->subject }}</td>
                                    <td>{{ $paper->class_section }}</td>
                                    <td>{{ $paper->exam_date ? $paper->exam_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>{{ $paper->exam_time ?: 'N/A' }}</td>
                                    <td>{{ $paper->duration_minutes ? $paper->duration_minutes . ' mins' : 'N/A' }}</td>
                                    <td>{{ $paper->total_marks ?: $paper->auto_calculated_total ?: 'N/A' }}</td>
                                    <td>{{ $paper->uploadedBy->name ?? 'N/A' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No upcoming exams found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        {{ $upcomingPapers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection