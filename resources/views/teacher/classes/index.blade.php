<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('teacher.dashboard') }}">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Panel
            </a>
            <div class="ms-auto">
                <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-school"></i> My Classes</h2>
                <p class="text-muted">View all classes and subjects assigned to you</p>
            </div>
        </div>

        <div class="row">
            @forelse($classesData as $classData)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-school"></i> {{ $classData['class_name'] }}
                            @if($classData['section_name'])
                                - {{ $classData['section_name'] }}
                            @endif
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($classData['is_class_teacher'])
                            <div class="alert alert-success">
                                <i class="fas fa-star"></i> <strong>Class Teacher</strong>
                            </div>
                        @endif

                        <h6 class="card-subtitle mb-3 text-muted">
                            <i class="fas fa-users"></i> Students: {{ $classData['student_count'] }}
                        </h6>

                        <h6><i class="fas fa-book"></i> Subjects Teaching:</h6>
                        <ul class="list-group list-group-flush mb-3">
                            @foreach($classData['subjects'] as $subject)
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success"></i> {{ $subject }}
                            </li>
                            @endforeach
                        </ul>

                        <div class="d-grid">
                            <a href="{{ route('teacher.classes.students', $classData['class_id']) }}" class="btn btn-primary">
                                <i class="fas fa-users"></i> View Students
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No classes assigned yet. Please contact the administrator.
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
