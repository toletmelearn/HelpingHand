<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - {{ $class->name }} - Teacher Panel</title>
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
                <a href="{{ route('teacher.classes') }}" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> My Classes
                </a>
                <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-users"></i> Students in {{ $class->name }}</h2>
                <p class="text-muted">Class Students List</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Student List ({{ $students->count() }} students)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Roll No</th>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                                <th>Mobile</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $student)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $student->roll_number ?? '-' }}</strong></td>
                                <td>{{ $student->admission_no }}</td>
                                <td>
                                    <i class="fas fa-user"></i> {{ $student->name }}
                                </td>
                                <td>{{ $student->father_name ?? '-' }}</td>
                                <td>{{ $student->mobile ?? '-' }}</td>
                                <td>
                                    @if($student->is_verified == 1)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-inbox"></i> No students found in this class
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
