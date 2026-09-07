@extends('layouts.admin')

@section('title', 'Teacher-Subject Assignment (incl. Class Teacher) - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-0"><i class="fas fa-user-tie"></i> Teacher-Subject Assignment</h2>
                    <p class="text-muted mb-0">Assign a teacher to a class/section/subject -- also where section-level Class Teacher status is set (the Timetable module reads this screen, not "Class Teacher Assignment").</p>
                </div>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Teacher-Subject Assignment</li>
                    </ol>
                </div>
            </div>
            <div class="text-end mt-2">
                <button type="button" class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#bulkAssignModal">
                    <i class="fas fa-layer-group"></i> Bulk Assign by Teacher
                </button>
                <a href="{{ route('admin.teacher-subject-assignments.create') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle"></i> Assign New
                </a>
                <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-lg" style="display:none;">
                    <i class="fas fa-trash"></i> Delete Selected (<span id="selected-count">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div id="bulk-delete-alert" class="alert d-none alert-dismissible fade show" role="alert">
        <span id="bulk-delete-alert-text"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Assignments</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.teacher-subject-assignments.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Teacher</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">All Teachers</option>
                                @foreach($teachers ?? [] as $teacher)
                                    <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Class</label>
                            <select name="class_id" class="form-select">
                                <option value="">All Classes</option>
                                @foreach($classes ?? [] as $class)
                                    <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Academic Year</label>
                            <select name="academic_year" class="form-select">
                                <option value="">All Years</option>
                                @php
                                    $currentYear = date('Y');
                                    $years = [$currentYear-1 . '-' . $currentYear, $currentYear . '-' . ($currentYear+1), ($currentYear+1) . '-' . ($currentYear+2)];
                                @endphp
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ request('academic_year') == $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $assignments->total() }}</h3>
                    <p class="mb-0">Total Assignments</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $assignments->where('is_class_teacher', true)->count() }}</h3>
                    <p class="mb-0">Class Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $assignments->unique('teacher_id')->count() }}</h3>
                    <p class="mb-0">Teachers Assigned</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0">{{ $assignments->unique('class_id')->count() }}</h3>
                    <p class="mb-0">Classes Covered</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignments Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-list"></i> Assignment List</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width:2.5rem;">
                                        <input type="checkbox" id="select-all-checkbox" class="form-check-input" aria-label="Select all assignments">
                                    </th>
                                    <th>#</th>
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Subject</th>
                                    <th>Class Teacher</th>
                                    <th>Primary Subject</th>
                                    <th>Academic Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $index => $assignment)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input row-checkbox" value="{{ $assignment->id }}" aria-label="Select assignment {{ $assignment->id }}">
                                        </td>
                                        <td>{{ $assignments->firstItem() + $index }}</td>
                                        <td>
                                            <strong>{{ $assignment->teacher->name ?? 'N/A' }}</strong>
                                            @if($assignment->coTeacher)
                                                <span class="text-muted">/ {{ $assignment->coTeacher->name }}</span>
                                            @endif
                                            <br><small class="text-muted">{{ $assignment->teacher->designation ?? 'Teacher' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $assignment->schoolClass->name ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            {{ $assignment->section->name ?? '-' }}
                                        </td>
                                        <td>
                                            <strong>{{ $assignment->subject->name ?? 'N/A' }}</strong>
                                            <br><small class="text-muted">{{ $assignment->subject->code ?? '' }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($assignment->is_class_teacher)
                                                <span class="badge bg-success"><i class="fas fa-star"></i> Yes</span>
                                            @else
                                                <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($assignment->is_primary_subject_teacher)
                                                <span class="badge bg-info"><i class="fas fa-check"></i> Yes</span>
                                            @else
                                                <span class="badge bg-light text-dark">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">{{ $assignment->academic_year ?? date('Y') . '-' . (date('Y') + 1) }}</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('admin.teacher-subject-assignments.edit', $assignment->id) }}" 
                                                   class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.teacher-subject-assignments.destroy', $assignment->id) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this assignment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <h5>No Assignments Found</h5>
                                                <p>Click "Assign New" to create your first teacher assignment.</p>
                                                <a href="{{ route('admin.teacher-subject-assignments.create') }}" class="btn btn-primary">
                                                    <i class="fas fa-plus-circle"></i> Create Assignment
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            Showing {{ $assignments->firstItem() ?? 0 }} to {{ $assignments->lastItem() ?? 0 }} of {{ $assignments->total() }} entries
                        </div>
                        <div>
                            {{ $assignments->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.assignments.teacher-subject._bulk_modal', [
    'teachers' => $teachers,
    'classes' => $classes,
    'sections' => \App\Models\Section::orderBy('name')->get(),
    'subjects' => \App\Models\Subject::orderBy('name')->get(),
])

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const alertBox = document.getElementById('bulk-delete-alert');
    const alertText = document.getElementById('bulk-delete-alert-text');

    if (!selectAllCheckbox || rowCheckboxes.length === 0) {
        return;
    }

    function updateBulkDeleteButton() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount;
        bulkDeleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
    }

    function showAlert(message, type) {
        alertText.textContent = message;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
    }

    selectAllCheckbox.addEventListener('change', function () {
        rowCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
        updateBulkDeleteButton();
    });

    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else if (Array.from(rowCheckboxes).every(cb => cb.checked)) {
                selectAllCheckbox.checked = true;
            }
            updateBulkDeleteButton();
        });
    });

    bulkDeleteBtn.addEventListener('click', function () {
        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            return;
        }

        if (!confirm(`Are you sure you want to delete ${selectedIds.length} assignment(s)? This cannot be undone.`)) {
            return;
        }

        bulkDeleteBtn.disabled = true;

        fetch('{{ route("admin.teacher-subject-assignments.bulk-delete") }}', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ ids: selectedIds }),
        })
            .then(response => response.json().then(json => ({ status: response.status, json })))
            .then(({ status, json }) => {
                if (status === 200 && json.success) {
                    showAlert(json.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showAlert(json.error || 'Something went wrong.', 'danger');
                    bulkDeleteBtn.disabled = false;
                }
            })
            .catch(() => {
                showAlert('Network error -- please try again.', 'danger');
                bulkDeleteBtn.disabled = false;
            });
    });
});
</script>
@endsection
