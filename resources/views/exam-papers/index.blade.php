<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Papers Management - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .paper-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .paper-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .paper-card.question {
            border-left-color: #007bff;
        }
        .paper-card.answer-key {
            border-left-color: #28a745;
        }
        .paper-card.solution {
            border-left-color: #17a2b8;
        }
        .paper-card.syllabus {
            border-left-color: #ffc107;
        }
        .paper-card.published {
            opacity: 1;
        }
        .paper-card.unpublished {
            opacity: 0.7;
        }
        .exam-type-badge {
            font-size: 0.8em;
        }
        .file-icon {
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-journal-text"></i> Exam Papers Management</h1>
            <div>
                <a href="{{ route('exam-papers.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Upload Paper
                </a>
                <a href="{{ route('exam-papers.available') }}" class="btn btn-info">
                    <i class="bi bi-eye"></i> Available Papers
                </a>
                <a href="{{ route('exam-papers.upcoming') }}" class="btn btn-warning">
                    <i class="bi bi-calendar-event"></i> Upcoming Exams
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Papers</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('exam-papers.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="subject" class="form-label">Subject</label>
                        <select class="form-select" id="subject" name="subject">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                    {{ $subject }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-2">
                        <label for="exam_type" class="form-label">Exam Type</label>
                        <select class="form-select" id="exam_type" name="exam_type">
                            <option value="">All Types</option>
                            @foreach($examTypes as $type)
                                <option value="{{ $type }}" {{ request('exam_type') == $type ? 'selected' : '' }}>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="paper_type" class="form-label">Paper Type</label>
                        <select class="form-select" id="paper_type" name="paper_type">
                            <option value="">All Types</option>
                            @foreach($paperTypes as $type)
                                <option value="{{ $type }}" {{ request('paper_type') == $type ? 'selected' : '' }}>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
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
                </form>
            </div>
        </div>

        <!-- Exam Papers Grid -->
        @if($examPapers->count() > 0)
            <div class="row">
                @foreach($examPapers as $paper)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card paper-card {{ strtolower(str_replace(' ', '-', $paper->paper_type)) }} {{ $paper->is_published ? 'published' : 'unpublished' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">{{ $paper->title }}</h6>
                                    <span class="badge bg-{{ $paper->getPaperTypeBadge() }}">
                                        {{ $paper->paper_type }}
                                    </span>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-book"></i> {{ $paper->subject }} | 
                                        <i class="bi bi-mortarboard"></i> {{ $paper->class_section }}
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <span class="badge bg-{{ $paper->getExamTypeInfo()['color'] }} exam-type-badge">
                                        <i class="bi {{ $paper->getExamTypeInfo()['icon'] }}"></i> {{ $paper->exam_type }}
                                    </span>
                                    
                                    @if($paper->is_published)
                                        <span class="badge bg-success exam-type-badge ms-1">
                                            <i class="bi bi-check-circle"></i> Published
                                        </span>
                                    @else
                                        <span class="badge bg-secondary exam-type-badge ms-1">
                                            <i class="bi bi-x-circle"></i> Draft
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-file-earmark-pdf"></i> {{ strtoupper($paper->file_extension) }} | 
                                        <i class="bi bi-file-arrow-down"></i> {{ $paper->download_count }} downloads | 
                                        <i class="bi bi-folder"></i> {{ $paper->getFileSizeFormatted() }}
                                    </small>
                                </div>
                                
                                @if($paper->exam_date)
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar-event"></i> 
                                            {{ \Carbon\Carbon::parse($paper->exam_date)->format('d M Y') }}
                                            @if($paper->exam_time)
                                                at {{ \Carbon\Carbon::parse($paper->exam_time)->format('h:i A') }}
                                            @endif
                                        </small>
                                    </div>
                                @endif
                                
                                <div class="mt-3">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <a href="{{ route('exam-papers.download', $paper) }}" 
                                           class="btn btn-outline-primary" title="Download">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                        <a href="{{ route('exam-papers.show', $paper) }}" 
                                           class="btn btn-outline-info" title="View Details">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                        <a href="{{ route('exam-papers.edit', $paper) }}" 
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <form action="{{ route('exam-papers.toggle-publish', $paper) }}" 
                                          method="PATCH" 
                                          style="display: inline;" 
                                          onsubmit="return confirm('Toggle publish status for this paper?');">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                                            <i class="bi bi-{{ $paper->is_published ? 'eye-slash' : 'eye' }}"></i> 
                                            {{ $paper->is_published ? 'Unpublish' : 'Publish' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="d-flex justify-content-center">
                {{ $examPapers->links() }}
            </div>
        @else
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> 
                No exam papers found. 
                <a href="{{ route('exam-papers.create') }}" class="alert-link">Upload your first exam paper!</a>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>