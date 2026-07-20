<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Marks - {{ $exam->name }} - Teacher Panel</title>
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
                <a href="{{ route('teacher.marks.index') }}" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Back
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
                <h2><i class="fas fa-clipboard-check"></i> Upload Marks</h2>
                <div class="card border-primary">
                    <div class="card-body">
                        <h5>{{ $exam->name }}</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Subject:</strong> {{ $exam->subject }}
                            </div>
                            <div class="col-md-3">
                                <strong>Class:</strong> {{ $exam->class_name ?? 'N/A' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Total Marks:</strong> {{ $exam->total_marks }}
                            </div>
                            <div class="col-md-3">
                                <strong>Passing Marks:</strong> {{ $exam->passing_marks ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('teacher.marks.store') }}">
            @csrf
            <input type="hidden" name="exam_id" value="{{ $exam->id }}">

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Students List ({{ $students->count() }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="10%">Roll No</th>
                                    <th width="25%">Student Name</th>
                                    <th width="15%">Admission No</th>
                                    <th width="15%">Theory Marks</th>
                                    <th width="15%">Practical Marks</th>
                                    <th width="15%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $index => $student)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $student->roll_number ?? '-' }}</strong></td>
                                    <td>
                                        <i class="fas fa-user"></i> {{ $student->name }}
                                        <input type="hidden" name="marks[{{ $index }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    <td>{{ $student->admission_no }}</td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="marks[{{ $index }}][theory_marks]"
                                               value="{{ $existingResults[$student->id]->theory_marks ?? '' }}"
                                               min="0" 
                                               max="{{ $exam->total_marks }}"
                                               step="0.01"
                                               placeholder="0"
                                               {{ $existingResults[$student->id]->is_locked ?? false ? 'readonly' : '' }}>
                                        @if($existingResults[$student->id]->is_locked ?? false)
                                        <small class="text-muted">Marks already submitted</small>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="marks[{{ $index }}][practical_marks]"
                                               value="{{ $existingResults[$student->id]->practical_marks ?? '' }}"
                                               min="0" 
                                               max="{{ $exam->total_marks }}"
                                               step="0.01"
                                               placeholder="0"
                                               {{ $existingResults[$student->id]->is_locked ?? false ? 'readonly' : '' }}>
                                        @if($existingResults[$student->id]->is_locked ?? false)
                                        <small class="text-muted">Marks already submitted</small>
                                        @endif
                                    </td>
                                    <td>
                                        <select name="marks[{{ $index }}][status]" class="form-select form-select-sm" {{ $existingResults[$student->id]->is_locked ?? false ? 'disabled' : '' }}>
                                            <option value="present" {{ (($existingResults[$student->id]->status ?? 'present') === 'present' ? 'selected' : '') }}>Present</option>
                                            <option value="absent" {{ (($existingResults[$student->id]->status ?? '') === 'absent' ? 'selected' : '') }}>Absent</option>
                                        </select>
                                        @if($existingResults[$student->id]->is_locked ?? false)
                                        <small class="text-muted">Locked</small>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-inbox"></i> No students found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($students->count() > 0)
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success btn-lg" 
                                {{ $existingResults->count() > 0 && $existingResults->first()->is_locked ? 'disabled' : '' }}>
                            <i class="fas fa-save"></i> 
                            {{ $existingResults->count() > 0 && $existingResults->first()->is_locked ? 'Marks Already Submitted' : 'Save & Submit Marks (Will be locked after submission)' }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </form>

        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Important:</strong> Once submitted, marks will be automatically locked and can only be edited by Exam Head or Admin.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
