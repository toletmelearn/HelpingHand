@extends('layouts.admin')

@section('title', 'Available Exam Papers')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-text"></i> Available Exam Papers</h2>
        <div>
            <a href="{{ route('exam-papers.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to All Papers
            </a>
            <a href="{{ route('exam-papers.upcoming') }}" class="btn btn-info">
                <i class="bi bi-calendar-event"></i> Upcoming Exams
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Papers</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('exam-papers.available') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label for="class_section" class="form-label">Class/Section</label>
                        <select class="form-select" id="class_section" name="class_section" required>
                            <option value="">Select Class</option>
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
                            <i class="bi bi-search"></i> Filter Papers
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Papers List -->
    @if(isset($papers) && $papers->count() > 0)
    <div class="row">
        @foreach($papers as $paper)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">{{ $paper->title }}</h6>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <strong>Subject:</strong> {{ $paper->subject }}<br>
                        <strong>Class:</strong> {{ $paper->class_section }}<br>
                        <strong>Type:</strong> 
                        <span class="badge bg-{{ $paper->getPaperTypeBadge() }}">
                            {{ $paper->paper_type }}
                        </span><br>
                        <strong>Exam:</strong> 
                        <span class="badge bg-{{ $paper->getExamTypeInfo()['color'] }}">
                            {{ $paper->exam_type }}
                        </span><br>
                        <strong>File:</strong> {{ $paper->file_name }}<br>
                        <strong>Size:</strong> {{ $paper->getFileSizeFormatted() }}
                    </p>
                    
                    @if($paper->exam_date)
                        <p class="text-muted small">
                            <i class="bi bi-calendar"></i> 
                            {{ $paper->exam_date->format('M j, Y') }}
                        </p>
                    @endif
                    
                    @if($paper->password_protected)
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-shield-lock"></i> Password Protected
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-download"></i> {{ $paper->download_count }} downloads
                        </small>
                        <div>
                            <a href="{{ route('exam-papers.download', $paper) }}" class="btn btn-sm btn-success">
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
        {{ $papers->links() }}
    </div>
    @elseif(request()->has('class_section'))
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No exam papers available for the selected class and filters.
    </div>
    @else
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Select a class/section to view available exam papers.
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
                    <a href="{{ route('exam-papers.upcoming') }}" class="btn btn-info w-100">
                        <i class="bi bi-calendar-check"></i> Upcoming Exams
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
</div>
@endsection
