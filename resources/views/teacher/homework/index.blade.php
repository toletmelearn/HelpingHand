<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homework - Teacher Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            <div class="col-md-8">
                <h2><i class="fas fa-book-reader"></i> Homework Management</h2>
                <p class="text-muted">Create and manage homework assignments for your classes</p>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createHomeworkModal">
                    <i class="fas fa-plus"></i> Create New Homework
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> My Homework Assignments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($homeworks as $homework)
                            <tr>
                                <td>{{ $homework->created_at->format('d M Y') }}</td>
                                <td>{{ $homework->schoolClass->name }}</td>
                                <td>{{ $homework->section->name ?? 'All' }}</td>
                                <td>
                                    <span class="badge bg-primary">{{ $homework->subject->name }}</span>
                                </td>
                                <td><strong>{{ $homework->title }}</strong></td>
                                <td>{{ $homework->due_date ? $homework->due_date->format('d M Y') : 'N/A' }}</td>
                                <td>
                                    @if($homework->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($homework->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('teacher.homework.show', $homework->id) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="{{ route('teacher.homework.edit', $homework->id) }}" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-inbox"></i> No homework created yet. Click "Create New Homework" to get started.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $homeworks->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Create Homework Modal -->
    <div class="modal fade" id="createHomeworkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Homework</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('teacher.homework.store') }}">
                    @csrf
                    
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class *</label>
                                <select name="class_id" class="form-select" required id="homeworkClassSelect">
                                    <option value="">Select Class</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Section</label>
                                <select name="section_id" class="form-select" id="homeworkSectionSelect">
                                    <option value="">All Sections</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="homework">Homework</option>
                                    <option value="notice">Notice</option>
                                    <option value="announcement">Announcement</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority *</label>
                                <select name="priority" class="form-select" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control" min="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control" required placeholder="e.g., Chapter 5 Exercise">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea name="description" class="form-control" rows="4" required placeholder="Enter homework details..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Create Homework
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // AJAX to load sections based on selected class
        $(document).ready(function() {
            $('#homeworkClassSelect').change(function() {
                var classId = $(this).val();
                
                if(classId != '') {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    
                    $.ajax({
                        url: '/teacher/get-sections-by-class/' + classId,
                        type: 'GET',
                        success: function(data) {
                            $('#homeworkSectionSelect').html(data);
                        },
                        error: function() {
                            alert('Error loading sections');
                        }
                    });
                } else {
                    $('#homeworkSectionSelect').html('<option value="">All Sections</option>');
                }
            });
        });
    </script>
</body>
</html>
