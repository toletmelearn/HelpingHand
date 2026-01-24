@extends('layouts.app')

@section('title', 'Exam Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Exam Details: {{ $exam->name }}</h4>
                    <div>
                        <a href="{{ route('admin.exams.edit', $exam) }}" class="btn btn-warning">Edit</a>
                        <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $exam->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $exam->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td>{{ $exam->exam_type }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $exam->class_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Subject:</strong></td>
                                    <td>{{ $exam->subject }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Academic Year:</strong></td>
                                    <td>{{ $exam->academic_year }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Term:</strong></td>
                                    <td>{{ $exam->term }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Timing & Marks</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Exam Date:</strong></td>
                                    <td>{{ $exam->exam_date->format('d M Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Start Time:</strong></td>
                                    <td>{{ $exam->start_time->format('H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>End Time:</strong></td>
                                    <td>{{ $exam->end_time->format('H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Marks:</strong></td>
                                    <td>{{ $exam->total_marks }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Passing Marks:</strong></td>
                                    <td>{{ $exam->passing_marks }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $exam->status == 'active' ? 'success' : ($exam->status == 'scheduled' ? 'info' : ($exam->status == 'cancelled' ? 'danger' : 'secondary')) }}">
                                            {{ ucfirst($exam->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td>{{ $exam->createdBy->name ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    @if($exam->description)
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h5>Description</h5>
                            <p>{{ $exam->description }}</p>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4">
                        <a href="{{ route('admin.results.index') }}" class="btn btn-primary">View Results</a>
                        <a href="{{ route('admin.results.create') }}?exam_id={{ $exam->id }}" class="btn btn-success">Add Results</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection