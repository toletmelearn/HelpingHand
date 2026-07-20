<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Homework - Teacher Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('teacher.dashboard') }}">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="{{ route('teacher.dashboard') }}">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="nav-link" href="{{ route('teacher.logout') }}">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-file-alt"></i> Homework Details</h2>
                <p class="text-muted">Detailed view of homework assignment</p>
            </div>
            <div class="col-md-4">
                <div class="text-end">
                    <a href="{{ route('teacher.homework.index') }}" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="{{ route('teacher.homework.edit', $homework->id) }}" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-book"></i> {{ $homework->title }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-school text-primary"></i> Class:</strong>
                                <p class="ms-3">{{ $homework->schoolClass->name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-layer-group text-primary"></i> Section:</strong>
                                <p class="ms-3">{{ $homework->section->name ?? 'All Sections' }}</p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-book-open text-primary"></i> Subject:</strong>
                                <p class="ms-3">{{ $homework->subject->name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-tag text-primary"></i> Type:</strong>
                                <p class="ms-3">
                                    @if($homework->type == 'homework')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-book"></i> Homework</span>
                                    @elseif($homework->type == 'notice')
                                        <span class="badge bg-info"><i class="fas fa-bell"></i> Notice</span>
                                    @elseif($homework->type == 'announcement')
                                        <span class="badge bg-success"><i class="fas fa-bullhorn"></i> Announcement</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-calendar-day text-primary"></i> Due Date:</strong>
                                <p class="ms-3">{{ $homework->due_date ? \Carbon\Carbon::parse($homework->due_date)->format('d M Y') : 'Not Set' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-flag text-primary"></i> Priority:</strong>
                                <p class="ms-3">
                                    @if($homework->priority == 'high')
                                        <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> High</span>
                                    @elseif($homework->priority == 'medium')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-minus"></i> Medium</span>
                                    @else
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Low</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-user text-primary"></i> Created By:</strong>
                                <p class="ms-3">{{ $homework->teacherLogin->name ?? 'Unknown' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-calendar-check text-primary"></i> Created Date:</strong>
                                <p class="ms-3">{{ \Carbon\Carbon::parse($homework->created_at)->format('d M Y h:i A') }}</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <strong><i class="fas fa-align-left text-primary"></i> Description:</strong>
                            <div class="mt-2 p-3 bg-light rounded">
                                {!! nl2br(e($homework->description)) !!}
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('teacher.homework.index') }}" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                            <a href="{{ route('teacher.homework.edit', $homework->id) }}" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Homework
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>