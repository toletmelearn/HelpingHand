@extends('layouts.app')

@section('title', 'Upcoming Exams')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-calendar-event"></i> Upcoming Exams</h2>
        <div>
            <a href="{{ route('exam-papers.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to All Papers
            </a>
            <a href="{{ route('exam-papers.available') }}" class="btn btn-info">
                <i class="bi bi-file-earmark-text"></i> Available Papers
            </a>
        </div>
    </div>

    <!-- Upcoming Exams -->
    @if($upcomingExams->count() > 0)
    <div class="row">
        @foreach($upcomingExams as $exam)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-{{ $exam->getExamTypeInfo()['color'] }}">
                <div class="card-header bg-{{ $exam->getExamTypeInfo()['color'] }} text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ $exam->title }}</h6>
                        <span class="badge bg-light text-dark">{{ $exam->getExamTypeInfo()['type'] }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <strong>Subject:</strong> {{ $exam->subject }}<br>
                        <strong>Class:</strong> {{ $exam->class_section }}<br>
                        <strong>Type:</strong> 
                        <span class="badge bg-{{ $exam->getPaperTypeBadge() }}">
                            {{ $exam->paper_type }}
                        </span><br>
                        <strong>Exam Date:</strong> 
                        <span class="text-danger">
                            <i class="bi bi-calendar"></i> {{ $exam->exam_date->format('F j, Y') }}
                        </span><br>
                        @if($exam->exam_time)
                            <strong>Exam Time:</strong> {{ $exam->exam_time->format('h:i A') }}<br>
                        @endif
                        <strong>Duration:</strong> {{ $exam->duration_minutes ? $exam->duration_minutes . ' minutes' : 'N/A' }}<br>
                        <strong>Total Marks:</strong> {{ $exam->total_marks ?? 'N/A' }}
                    </p>
                    
                    @if($exam->instructions)
                        <div class="alert alert-info py-2">
                            <small><strong>Instructions:</strong> {{ Str::limit($exam->instructions, 100) }}</small>
                        </div>
                    @endif
                    
                    @if($exam->password_protected)
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-shield-lock"></i> Password Protected
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> 
                            {{ $exam->exam_date->diffForHumans() }}
                        </small>
                        <div>
                            <a href="{{ route('exam-papers.download', $exam) }}" class="btn btn-sm btn-success">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="d-flex justify-content-center">
        {{ $upcomingExams->links() }}
    </div>
    @else
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No upcoming exams scheduled within the next 30 days.
    </div>
    @endif

    <!-- Quick Access -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <a href="{{ route('exam-papers.available') }}" class="btn btn-info w-100">
                        <i class="bi bi-file-earmark-text"></i> Available Papers
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('exam-papers.search') }}" class="btn btn-warning w-100">
                        <i class="bi bi-search"></i> Search Papers
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('exam-papers.create') }}" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Upload Paper
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="card mt-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-calendar3"></i> Exam Calendar Preview</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Exam</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcomingExams as $exam)
                        <tr>
                            <td>{{ $exam->exam_date->format('M j, Y') }}</td>
                            <td>{{ $exam->title }}</td>
                            <td>{{ $exam->class_section }}</td>
                            <td>{{ $exam->subject }}</td>
                            <td>
                                <span class="badge bg-{{ $exam->getExamTypeInfo()['color'] }}">
                                    {{ $exam->exam_type }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('exam-papers.download', $exam) }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection