@extends('layouts.admin')

@section('title', 'Create Certificate')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Create Certificate</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.certificates.index') }}">Certificates</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">New Certificate</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.certificates.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="certificate_type" class="form-label">Certificate Type *</label>
                                    <select name="certificate_type" id="certificate_type" class="form-control @error('certificate_type') is-invalid @enderror" required>
                                        <option value="">Select Certificate Type</option>
                                        <option value="tc" {{ old('certificate_type') == 'tc' ? 'selected' : '' }}>Transfer Certificate (TC)</option>
                                        <option value="bonafide" {{ old('certificate_type') == 'bonafide' ? 'selected' : '' }}>Bonafide Certificate</option>
                                        <option value="character" {{ old('certificate_type') == 'character' ? 'selected' : '' }}>Character Certificate</option>
                                        <option value="experience" {{ old('certificate_type') == 'experience' ? 'selected' : '' }}>Experience Certificate</option>
                                    </select>
                                    @error('certificate_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="recipient_type" class="form-label">Recipient Type *</label>
                                    <select name="recipient_type" id="recipient_type" class="form-control @error('recipient_type') is-invalid @enderror" required onchange="toggleRecipientFields()">
                                        <option value="">Select Recipient Type</option>
                                        <option value="App\Models\Student" {{ old('recipient_type') == 'App\Models\Student' ? 'selected' : '' }}>Student</option>
                                        <option value="App\Models\Teacher" {{ old('recipient_type') == 'App\Models\Teacher' ? 'selected' : '' }}>Teacher</option>
                                    </select>
                                    @error('recipient_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3" id="student_field" style="display: none;">
                                    <label for="recipient_id_student" class="form-label">Select Student *</label>
                                    <select name="recipient_id" id="recipient_id_student" class="form-control @error('recipient_id') is-invalid @enderror">
                                        <option value="">Select Student</option>
                                        @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('recipient_id') == $student->id ? 'selected' : '' }}>
                                                {{ $student->name }} (Roll: {{ $student->roll_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('recipient_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3" id="teacher_field" style="display: none;">
                                    <label for="recipient_id_teacher" class="form-label">Select Teacher *</label>
                                    <select name="recipient_id" id="recipient_id_teacher" class="form-control @error('recipient_id') is-invalid @enderror">
                                        <option value="">Select Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('recipient_id') == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }} (Employee ID: {{ $teacher->employee_id }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('recipient_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="template_id" class="form-label">Template</label>
                                    <select name="template_id" id="template_id" class="form-control @error('template_id') is-invalid @enderror">
                                        <option value="">Select Template (Optional)</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template->id }}" {{ old('template_id') == $template->id ? 'selected' : '' }}>
                                                {{ $template->name }} ({{ strtoupper($template->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('template_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content_data" class="form-label">Certificate Content *</label>
                            <div class="border p-3 rounded bg-light">
                                <small class="text-muted">Enter the certificate content with appropriate fields:</small>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label">Recipient Name</label>
                                            <input type="text" name="content_data[recipient_name]" class="form-control" value="{{ old('content_data.recipient_name') }}" placeholder="Enter recipient name">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Date</label>
                                            <input type="text" name="content_data[date]" class="form-control" value="{{ old('content_data.date', date('d/m/Y')) }}" placeholder="Enter date">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Class</label>
                                            <input type="text" name="content_data[class]" class="form-control" value="{{ old('content_data.class') }}" placeholder="Enter class">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label">Course/Position</label>
                                            <input type="text" name="content_data[course_position]" class="form-control" value="{{ old('content_data.course_position') }}" placeholder="Enter course or position">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Duration</label>
                                            <input type="text" name="content_data[duration]" class="form-control" value="{{ old('content_data.duration') }}" placeholder="Enter duration">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Additional Details</label>
                                            <input type="text" name="content_data[details]" class="form-control" value="{{ old('content_data.details') }}" placeholder="Enter additional details">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Certificate Body</label>
                                    <textarea name="content_data[body]" class="form-control" rows="4" placeholder="Enter the main body of the certificate">{{ old('content_data.body') }}</textarea>
                                </div>
                            </div>
                            @error('content_data')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.certificates.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Certificate</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRecipientFields() {
    const recipientType = document.getElementById('recipient_type').value;
    const studentField = document.getElementById('student_field');
    const teacherField = document.getElementById('teacher_field');
    
    // Hide both fields initially
    studentField.style.display = 'none';
    teacherField.style.display = 'none';
    
    // Show the appropriate field
    if (recipientType === 'App\\Models\\Student') {
        studentField.style.display = 'block';
        document.getElementById('recipient_id_student').required = true;
        document.getElementById('recipient_id_teacher').required = false;
    } else if (recipientType === 'App\\Models\\Teacher') {
        teacherField.style.display = 'block';
        document.getElementById('recipient_id_teacher').required = true;
        document.getElementById('recipient_id_student').required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRecipientFields();
});
</script>
@endsection
