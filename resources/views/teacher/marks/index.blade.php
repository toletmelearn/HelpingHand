<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Marks - Teacher Panel</title>
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
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-clipboard-check"></i> Upload Marks</h2>
                <p class="text-muted">Select an exam to upload or update marks</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Available Exams for Your Subjects</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Exam Name</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Total Marks</th>
                                <th>Exam Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exams as $exam)
                            <tr>
                                <td><strong>{{ $exam->name }}</strong></td>
                                <td>
                                    <span class="badge bg-primary">{{ $exam->subject }}</span>
                                </td>
                                <td>{{ $exam->class_name ?? 'N/A' }}</td>
                                <td>{{ $exam->total_marks }}</td>
                                <td>{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('teacher.marks.show', $exam->id) }}" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-upload"></i> Upload Marks
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-inbox"></i> No exams available for your assigned subjects
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $exams->links() }}
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> Important Information</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><strong>Marks Locking:</strong> Once you submit marks, they will be locked automatically</li>
                    <li><strong>Editing:</strong> Locked marks can only be edited by the Exam Head or Admin</li>
                    <li><strong>Grade Calculation:</strong> Grades are calculated automatically based on percentage</li>
                    <li><strong>Validation:</strong> You can only upload marks for subjects assigned to you</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
