<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Homework - Teacher Panel</title>
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
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-plus-circle"></i> Create New Homework</h2>
                <p class="text-muted">Fill in the details for your new homework assignment</p>
            </div>
            <div class="col-md-4">
                <div class="text-end">
                    <a href="{{ route('teacher.homework.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-book"></i> Homework Details</h5>
                    </div>
                    <div class="card-body">
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

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Class *</label>
                                    <select name="class_id" class="form-select" required id="homeworkClassSelect">
                                        <option value="">Select Class</option>
                                        @foreach($assignments as $assignment)
                                            <option value="{{ $assignment->schoolClass->id }}">{{ $assignment->schoolClass->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Section</label>
                                    <select name="section_id" class="form-select" id="homeworkSectionSelect">
                                        <option value="">All Sections</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject *</label>
                                    <select name="subject_id" class="form-select" required>
                                        <option value="">Select Subject</option>
                                        @foreach($assignments as $assignment)
                                            <option value="{{ $assignment->subject->id }}">{{ $assignment->subject->name }}</option>
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
                            </div>

                            <div class="row">
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
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control" required placeholder="e.g., Chapter 5 Exercise">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description *</label>
                                <textarea name="description" class="form-control" rows="4" required placeholder="Enter homework details..."></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ route('teacher.homework.index') }}" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Create Homework
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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