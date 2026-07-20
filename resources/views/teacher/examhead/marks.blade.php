<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Marks - Exam Head - Teacher Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('teacher.dashboard') }}">
                <i class="fas fa-user-shield"></i> Exam Head Panel
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
                <h2><i class="fas fa-clipboard-check"></i> Review Submitted Marks</h2>
                <p class="text-muted">As Exam Head, you can review and approve marks submitted by teachers</p>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Pending Reviews</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Exam Name</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Teacher</th>
                                <th>Submitted Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($results as $result)
                            <tr>
                                <td><strong>{{ $result->exam->name }}</strong></td>
                                <td>
                                    <span class="badge bg-primary">{{ $result->subject }}</span>
                                </td>
                                <td>{{ $result->exam->schoolClass->name ?? 'N/A' }}</td>
                                <td>
                                    <i class="fas fa-user"></i> {{ $result->uploadedByTeacher->name ?? 'Unknown' }}
                                </td>
                                <td>{{ $result->uploaded_at ? $result->uploaded_at->format('d M Y H:i') : 'N/A' }}</td>
                                <td>
                                    @if($result->is_locked)
                                        <span class="badge bg-warning">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">Unlocked</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('teacher.examhead.marks.review', $result->exam_id) }}" 
                                       class="btn btn-sm btn-danger">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-check-circle"></i> No pending reviews. All marks have been reviewed!
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $results->links() }}
                </div>
            </div>
        </div>

        <div class="card mt-4 border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> Exam Head Privileges</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><strong>Review Authority:</strong> You can review marks submitted by all teachers</li>
                    <li><strong>Edit Locked Marks:</strong> You have permission to edit marks even after they're locked</li>
                    <li><strong>Approval Rights:</strong> Approve or request corrections for submitted marks</li>
                    <li><strong>Quality Control:</strong> Ensure accuracy and consistency of all exam results</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
