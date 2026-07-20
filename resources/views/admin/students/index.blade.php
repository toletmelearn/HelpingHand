@extends('layouts.admin')

@section('title', 'Students Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">
                        @if(isset($showingStudents) && $showingStudents)
                            Students List
                        @else
                            Students by Class & Section
                        @endif
                    </h4>
                    <div>
                        <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Student
                        </a>
                        @if(Route::has('imports.wizard'))
                            <a href="{{ route('imports.wizard', ['module' => 'students']) }}" class="btn btn-outline-secondary" title="Add many students at once from a spreadsheet">
                                <i class="fas fa-file-upload"></i> Bulk Import Instead
                            </a>
                        @endif
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="filterClass" class="form-label">Class</label>
                            <select name="class_id" id="filterClass" class="form-select" onchange="applyFilters()">
                                <option value="">All Classes</option>
                                @foreach($classList as $class)
                                    <option value="{{ $class->id }}" 
                                        {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="filterSection" class="form-label">Section</label>
                            <select name="section_id" id="filterSection" class="form-select" onchange="applyFilters()">
                                <option value="">All Sections</option>
                                @foreach($sections as $sec)
                                    <option value="{{ $sec->id }}" 
                                        {{ (string)($selectedSectionId ?? request('section_id')) === (string)$sec->id ? 'selected' : '' }}>
                                        {{ $sec->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="filterSearch" class="form-label">Search (Name/Admission/Mobile)</label>
                            <input type="text" name="search" id="filterSearch" class="form-control" 
                                   placeholder="Search students..." value="{{ request('search') }}" onkeypress="if(event.key==='Enter'){applyFilters();}">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label><br>
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    @php
                        $canBulkDeleteStudents = auth()->user()->hasRole('admin') || auth()->user()->hasPermission('delete-students');
                    @endphp
                    @if(isset($showingStudents) && $showingStudents)
                        <!-- Students Table View -->
                        @if($canBulkDeleteStudents)
                        <form id="bulkDeleteForm" action="{{ route('admin.students.bulk-destroy') }}" method="POST">
                            @csrf
                        </form>
                        <div class="mb-2">
                            <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" disabled onclick="submitBulkDelete()">
                                <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                            </button>
                        </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        @if($canBulkDeleteStudents)
                                        <th><input type="checkbox" id="selectAllStudents"></th>
                                        @endif
                                        <th>Photo</th>
                                        <th>Roll No</th>
                                        <th>Name</th>
                                        <th>Admission No</th>
                                        <th>Father Name</th>
                                        <th>Mobile</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($students as $student)
                                    @php $canDeleteThisStudent = auth()->user()->can('delete', $student); @endphp
                                    <tr>
                                        @if($canDeleteThisStudent)
                                        <td><input type="checkbox" class="student-select-checkbox" value="{{ $student->id }}" onchange="updateBulkDeleteState()"></td>
                                        @elseif($canBulkDeleteStudents)
                                        <td></td>
                                        @endif
                                        <td>
                                            <img src="{{ $student->photo_url }}" alt="{{ $student->name }}"
                                                 class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                        </td>
                                        <td>{{ $student->roll_number ?: 'N/A' }}</td>
                                        <td>{{ $student->name }}</td>
                                        <td>{{ $student->admission_no ?: 'N/A' }}</td>
                                        <td>{{ $student->father_name }}</td>
                                        <td>{{ $student->mobile ?: 'N/A' }}</td>
                                        <td>{{ $student->schoolClass->name ?? 'N/A' }}</td>
                                        <td>{{ $student->section ?: 'N/A' }}</td>
                                        <td>
                                            <a href="{{ route('admin.students.show', $student->id) }}"
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            @can('update', $student)
                                            <a href="{{ route('admin.students.edit', $student->id) }}"
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            @endcan
                                            @if(\App\Helpers\FieldPermissionHelper::canEditField('student', 'photo'))
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#changeStudentPhotoModal"
                                                    data-photo-action="{{ route('students.photo.update', $student->id) }}"
                                                    data-photo-name="{{ $student->name }}">
                                                <i class="fas fa-camera"></i> Photo
                                            </button>
                                            @endif
                                            @can('delete', $student)
                                            <form action="{{ route('admin.students.destroy', $student->id) }}"
                                                  method="POST" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete this student?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center">No students found</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($students->count() > 0)
                        <div class="alert alert-info">
                            <strong>Total Students:</strong> {{ $students->count() }}
                        </div>
                        @endif
                        
                    @else
                        @if(auth()->user()->hasRole('admin'))
                        <!-- Manage Classes -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Manage Classes</h5>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
                                <i class="fas fa-plus"></i> Add New Class
                            </button>
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Class</th>
                                        <th class="text-center">Total Students (all sections)</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($classTotals as $ct)
                                    <tr>
                                        <td>{{ $ct->schoolClass->name ?? 'N/A' }}</td>
                                        <td class="text-center">{{ $ct->total }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="openDeleteClassModal({{ $ct->class_id }}, '{{ addslashes($ct->schoolClass->name ?? '') }}', {{ $ct->total }})">
                                                <i class="fas fa-trash"></i> Delete Class &amp; Students
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="3" class="text-center text-muted">No classes found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @endif

                        <!-- Class-Section Grouped View -->
                        <div class="row">
                            @forelse($classSections as $cs)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $cs->schoolClass->name ?? 'N/A' }}</h5>
                                        <p class="card-text">
                                            <strong>Section:</strong> {{ $cs->section->name ?? 'N/A' }}<br>
                                            <strong>Students:</strong> {{ $cs->total }}
                                        </p>
                                        <a href="{{ route('admin.students.index', array_filter(['class_id' => $cs->class_id, 'section_id' => $cs->section_id, 'section' => $cs->section_id ? null : $cs->section], fn($value) => $value !== null && $value !== '')) }}"
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12">
                                <div class="alert alert-warning text-center">
                                    No student records found in the system.
                                </div>
                            </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->hasRole('admin') && isset($showingStudents) && !$showingStudents)
<!-- Add New Class -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.school-classes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Class 13" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order / Level</label>
                        <input type="number" name="class_order" class="form-control" min="1" placeholder="Position in the class sequence, e.g. 16" required>
                        <small class="text-muted">Determines ordering and which class students promote into next -- must be unique.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Academic Session (optional)</label>
                        <select name="academic_session_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach($academicSessions ?? [] as $session)
                                <option value="{{ $session->id }}">{{ $session->name ?? $session->id }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Class & Students -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteClassForm">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete Class &amp; Students</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">
                        This permanently removes the class "<span id="deleteClassName"></span>" and all
                        <span id="deleteClassCount"></span> student(s) currently in it -- across every section.
                        This cannot be undone from the UI.
                    </p>
                    <p>Type the class name exactly to confirm:</p>
                    <input type="text" id="deleteClassConfirmInput" class="form-control" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteClassSubmitBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Class &amp; Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let deleteClassExpectedName = '';

function openDeleteClassModal(classId, className, total) {
    deleteClassExpectedName = className;
    document.getElementById('deleteClassName').textContent = className;
    document.getElementById('deleteClassCount').textContent = total;
    document.getElementById('deleteClassConfirmInput').value = '';
    document.getElementById('deleteClassSubmitBtn').disabled = true;
    document.getElementById('deleteClassForm').action = '/admin/school-classes/' + classId + '/with-students';
    new bootstrap.Modal(document.getElementById('deleteClassModal')).show();
}

document.getElementById('deleteClassConfirmInput').addEventListener('input', function () {
    document.getElementById('deleteClassSubmitBtn').disabled = (this.value !== deleteClassExpectedName);
});
</script>
@endif

@if(\App\Helpers\FieldPermissionHelper::canEditField('student', 'photo'))
<div class="modal fade" id="changeStudentPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="changeStudentPhotoForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Change Photo &mdash; <span id="changeStudentPhotoName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" required>
                    <small class="text-muted">JPEG, PNG, GIF, WEBP or BMP, up to 8MB.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('changeStudentPhotoModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('changeStudentPhotoForm').action = button.getAttribute('data-photo-action');
    document.getElementById('changeStudentPhotoName').textContent = button.getAttribute('data-photo-name');
});
</script>
@endif

<script>
function applyFilters() {
    const filterClass = document.getElementById('filterClass');
    const filterSection = document.getElementById('filterSection');
    const filterSearch = document.getElementById('filterSearch');
    
    let url = window.location.origin + '/admin/students';
    const params = new URLSearchParams();
    
    if (filterClass.value) {
        params.append('class_id', filterClass.value);
    }
    if (filterSection.value) {
        params.append('section_id', filterSection.value);
    }
    if (filterSearch.value) {
        params.append('search', filterSearch.value);
    }
    
    window.location.href = url + '?' + params.toString();
}

function updateBulkDeleteState() {
    const btn = document.getElementById('bulkDeleteBtn');
    if (!btn) return;
    const checked = document.querySelectorAll('.student-select-checkbox:checked');
    btn.disabled = checked.length === 0;
    document.getElementById('selectedCount').textContent = checked.length;
}

function submitBulkDelete() {
    const checked = document.querySelectorAll('.student-select-checkbox:checked');
    if (checked.length === 0) return;
    if (!confirm(`Are you sure you want to delete ${checked.length} selected student(s)? This cannot be undone easily.`)) return;

    const form = document.getElementById('bulkDeleteForm');
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });
    form.submit();
}

document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllStudents');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-select-checkbox').forEach(cb => cb.checked = selectAll.checked);
            updateBulkDeleteState();
        });
    }
});
</script>
@endsection
