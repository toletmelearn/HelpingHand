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
                        <!-- Class-Section Grouped View -->
                        <div class="row">
                            @forelse($classSections as $cs)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $cs->schoolClass->name ?? 'N/A' }}</h5>
                                        <p class="card-text">
                                            <strong>Section:</strong> {{ $cs->section ?: 'N/A' }}<br>
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
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                    <small class="text-muted">JPEG, PNG or GIF, up to 2MB.</small>
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
