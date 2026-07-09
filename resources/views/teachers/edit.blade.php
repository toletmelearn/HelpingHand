<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .required:after { content: " *"; color: red; }
        .profile-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>✏️ Edit Teacher: {{ $teacher->name }}</h2>
            <div>
                <a href="{{ url('/admin/teachers') }}" class="btn btn-secondary">
                    ← Back to Teachers
                </a>
                <a href="{{ url('/admin/teachers/' . $teacher->id) }}" class="btn btn-info ms-2">
                    👁️ View
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                ✅ {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <h5>Please fix these errors:</h5>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Edit Form -->
        <div class="card shadow">
            <div class="card-body">
                <form action="{{ route('admin.teachers.update', $teacher->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <!-- Left Column: Profile & Personal Info -->
                        <div class="col-md-4">
                            <div class="text-center mb-4">
                                <!-- Profile Image Preview -->
                                @if($teacher->profile_image)
                                    <img id="profilePreview" 
                                         src="{{ asset('storage/' . $teacher->profile_image) }}" 
                                         class="profile-preview" 
                                         alt="Profile Preview">
                                @else
                                    <div id="profilePreview" class="profile-preview bg-secondary text-white d-flex align-items-center justify-content-center fs-1">
                                        {{ substr($teacher->name, 0, 1) }}
                                    </div>
                                @endif
                                
                                <!-- Image Upload -->
                                <div class="mb-3">
                                    <label class="form-label">Change Profile Photo</label>
                                    <input type="file" name="profile_image" 
                                           class="form-control" 
                                           accept="image/*"
                                           onchange="previewImage(event)">
                                    <small class="text-muted">Max 2MB, JPG/PNG format</small>
                                </div>
                            </div>

                            <!-- Quick Info Card -->
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">📊 Quick Info</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Teacher ID:</strong> #{{ $teacher->id }}</p>
                                    <p><strong>Joined:</strong> {{ \Carbon\Carbon::parse($teacher->date_of_joining)->format('d M Y') }}</p>
                                    <p><strong>Experience:</strong> {{ $teacher->experience }} years</p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-{{ $teacher->status == 'active' ? 'success' : ($teacher->status == 'on_leave' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($teacher->status) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Editable Fields -->
                        <div class="col-md-8">
                            <!-- Personal Details -->
                            <h5 class="border-bottom pb-2 mb-3">👤 Personal Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Full Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="{{ old('name', $teacher->name) }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="{{ old('email', $teacher->email) }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="{{ old('phone', $teacher->phone) }}" 
                                           maxlength="10" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Aadhar Number</label>
                                    <input type="text" name="aadhar_number" class="form-control"
                                           value="{{ old('aadhar_number', $teacher->aadhar_number) }}"
                                           maxlength="12" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Relative Name (Father's / Husband's / Wife's Name)</label>
                                    <input type="text" name="relative_name" class="form-control"
                                           value="{{ old('relative_name', $teacher->relative_name) }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Emergency Contact Number</label>
                                    <input type="tel" name="emergency_contact" class="form-control"
                                           value="{{ old('emergency_contact', $teacher->emergency_contact) }}" maxlength="10">
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label required">Address for Correspondence</label>
                                    <textarea name="address" class="form-control" rows="3" required>{{ old('address', $teacher->address) }}</textarea>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">Permanent Address</label>
                                    <textarea name="permanent_address" class="form-control" rows="3">{{ old('permanent_address', $teacher->permanent_address) }}</textarea>
                                </div>
                            </div>

                            <!-- Professional Details -->
                            <h5 class="border-bottom pb-2 mb-3 mt-4">📚 Professional Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Qualification</label>
                                    <select name="qualification" class="form-select" required>
                                        <option value="">Select Qualification</option>
                                        @foreach($qualifications as $qual)
                                            <option value="{{ $qual }}" 
                                                {{ old('qualification', $teacher->qualification) == $qual ? 'selected' : '' }}>
                                                {{ $qual }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Subject Specialization</label>
                                    <select name="subject_specialization" class="form-select" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject }}" 
                                                {{ old('subject_specialization', $teacher->subject_specialization) == $subject ? 'selected' : '' }}>
                                                {{ $subject }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Educational Qualification (B.Ed with Subjects / Other)</label>
                                    <input type="text" name="educational_qualification" class="form-control"
                                           value="{{ old('educational_qualification', $teacher->educational_qualification) }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Date of Joining</label>
                                    <input type="date" name="date_of_joining" class="form-control" 
                                           value="{{ old('date_of_joining', $teacher->date_of_joining) }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Monthly Salary (₹)</label>
                                    <input type="number" name="salary" class="form-control" 
                                           value="{{ old('salary', $teacher->salary) }}" 
                                           step="0.01" min="0" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" {{ old('status', $teacher->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $teacher->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="on_leave" {{ old('status', $teacher->status) == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                                    </select>
                                </div>
                            </div>
<!-- ========== ADD THIS CODE TO EDIT FORM ========== -->

<!-- Teaching Details -->
<div class="row mt-3">
    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">🏫 Teaching Details</h5>
        
        <div class="mb-3">
            <label class="form-label required">Wing</label>
            <select name="wing" class="form-select" required>
                <option value="">Select Wing</option>
                <option value="primary" {{ old('wing', $teacher->wing) == 'primary' ? 'selected' : '' }}>
                    Primary Wing (Nursery - Class 2)
                </option>
                <option value="junior" {{ old('wing', $teacher->wing) == 'junior' ? 'selected' : '' }}>
                    Junior Wing (Class 3 - Class 5)
                </option>
                <option value="senior" {{ old('wing', $teacher->wing) == 'senior' ? 'selected' : '' }}>
                    Senior Wing (Class 6 - Class 12)
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label required">Teacher Type</label>
            <select name="teacher_type" class="form-select" required>
                <option value="">Select Type</option>
                <option value="PRT" {{ old('teacher_type', $teacher->teacher_type) == 'PRT' ? 'selected' : '' }}>
                    PRT (Primary Teacher)
                </option>
                <option value="TGT" {{ old('teacher_type', $teacher->teacher_type) == 'TGT' ? 'selected' : '' }}>
                    TGT (Trained Graduate Teacher)
                </option>
                <option value="PGT" {{ old('teacher_type', $teacher->teacher_type) == 'PGT' ? 'selected' : '' }}>
                    PGT (Post Graduate Teacher)
                </option>
                <option value="Other" {{ old('teacher_type', $teacher->teacher_type) == 'Other' ? 'selected' : '' }}>
                    Other
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Subjects (Can teach multiple)</label>
            <select name="subjects[]" class="form-select" multiple>
                @php
                    $selectedSubjects = old('subjects', $teacher->subjects ?? []);
                    if (is_string($selectedSubjects)) {
                        $selectedSubjects = json_decode($selectedSubjects, true) ?? [];
                    }
                @endphp
                <option value="Mathematics" {{ in_array('Mathematics', $selectedSubjects) ? 'selected' : '' }}>Mathematics</option>
                <option value="Science" {{ in_array('Science', $selectedSubjects) ? 'selected' : '' }}>Science</option>
                <option value="English" {{ in_array('English', $selectedSubjects) ? 'selected' : '' }}>English</option>
                <option value="Hindi" {{ in_array('Hindi', $selectedSubjects) ? 'selected' : '' }}>Hindi</option>
                <option value="Social Studies" {{ in_array('Social Studies', $selectedSubjects) ? 'selected' : '' }}>Social Studies</option>
                <option value="Physics" {{ in_array('Physics', $selectedSubjects) ? 'selected' : '' }}>Physics</option>
                <option value="Chemistry" {{ in_array('Chemistry', $selectedSubjects) ? 'selected' : '' }}>Chemistry</option>
                <option value="Biology" {{ in_array('Biology', $selectedSubjects) ? 'selected' : '' }}>Biology</option>
                <option value="Computer Science" {{ in_array('Computer Science', $selectedSubjects) ? 'selected' : '' }}>Computer Science</option>
                <option value="Physical Education" {{ in_array('Physical Education', $selectedSubjects) ? 'selected' : '' }}>Physical Education</option>
                <option value="Arts" {{ in_array('Arts', $selectedSubjects) ? 'selected' : '' }}>Arts</option>
                <option value="Music" {{ in_array('Music', $selectedSubjects) ? 'selected' : '' }}>Music</option>
                <option value="Dance" {{ in_array('Dance', $selectedSubjects) ? 'selected' : '' }}>Dance</option>
            </select>
            <small class="text-muted">Hold Ctrl to select multiple subjects</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Designation</label>
            <input type="text" name="designation" class="form-control" 
                   value="{{ old('designation', $teacher->designation ?? 'Teacher') }}">
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">👤 Personal & Professional</h5>
        
        <div class="mb-3">
            <label class="form-label required">Gender</label>
            <select name="gender" class="form-select" required>
                <option value="">Select Gender</option>
                <option value="male" {{ old('gender', $teacher->gender) == 'male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ old('gender', $teacher->gender) == 'female' ? 'selected' : '' }}>Female</option>
                <option value="other" {{ old('gender', $teacher->gender) == 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" 
                   value="{{ old('date_of_birth', $teacher->date_of_birth) }}">
        </div>

        <div class="mb-3">
            <label class="form-label required">Employment Type</label>
            <select name="employment_type" class="form-select" required>
                <option value="permanent" {{ old('employment_type', $teacher->employment_type) == 'permanent' ? 'selected' : '' }}>Permanent</option>
                <option value="contract" {{ old('employment_type', $teacher->employment_type) == 'contract' ? 'selected' : '' }}>Contract</option>
                <option value="temporary" {{ old('employment_type', $teacher->employment_type) == 'temporary' ? 'selected' : '' }}>Temporary</option>
                <option value="guest" {{ old('employment_type', $teacher->employment_type) == 'guest' ? 'selected' : '' }}>Guest Faculty</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Employee ID</label>
            <input type="text" name="employee_id" class="form-control" 
                   value="{{ old('employee_id', $teacher->employee_id) }}" 
                   placeholder="SCH/TCH/2024/001">
        </div>

        <div class="mb-3">
            <label class="form-label">UAN Number (PF)</label>
            <input type="text" name="uan_number" class="form-control" 
                   value="{{ old('uan_number', $teacher->uan_number) }}">
        </div>

        <div class="mb-3">
            <label class="form-label">PAN Number</label>
            <input type="text" name="pan_number" class="form-control" 
                   value="{{ old('pan_number', $teacher->pan_number) }}">
        </div>
    </div>
</div>

<!-- ========== END OF ADDED CODE ========== -->
                            <!-- HR Master Data -->
                            <h5 class="border-bottom pb-2 mb-3 mt-4">🗂️ HR Master Data</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Classes Taught with Subjects</label>
                                    <textarea name="classes_taught" class="form-control" rows="2"
                                              placeholder="e.g., Class 6-A Maths, Class 7-B Science">{{ old('classes_taught', $teacher->classes_taught) }}</textarea>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">No. of Periods</label>
                                    <input type="number" name="no_of_periods" class="form-control"
                                           value="{{ old('no_of_periods', $teacher->no_of_periods) }}" min="0" max="60">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Class &amp; Section</label>
                                    <input type="text" name="class_section" class="form-control"
                                           value="{{ old('class_section', $teacher->class_section) }}" placeholder="e.g., 8-B">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Responsibilities</label>
                                    <textarea name="responsibilities" class="form-control" rows="2">{{ old('responsibilities', $teacher->responsibilities) }}</textarea>
                                </div>
                            </div>
                            <!-- Exam Control Toggles -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="border-bottom pb-2 mb-3">🎓 Examination Control</h5>
                                    <div class="card bg-light p-3">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="isExamHead" 
                                                   name="is_exam_head" value="1"
                                                   {{ old('is_exam_head', $teacher->is_exam_head) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="isExamHead">
                                                ☑️ Make Exam Head
                                            </label>
                                            <p class="text-muted small mb-0">
                                                Grant this teacher permission to view result monitoring, edit marks, and approve results
                                            </p>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="isExamCellMember" 
                                                   name="is_exam_cell_member" value="1"
                                                   {{ old('is_exam_cell_member', $teacher->is_exam_cell_member) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="isExamCellMember">
                                                ☑️ Make Exam Cell Member
                                            </label>
                                            <p class="text-muted small mb-0">
                                                Grant this teacher permission to manage exam arrangements, invigilation duties, and relieving shifts
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Financial & Additional Details -->
                            <h5 class="border-bottom pb-2 mb-3 mt-4">💰 Financial & Additional Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bank Account Number</label>
                                    <input type="text" name="bank_account_number" class="form-control" 
                                           value="{{ old('bank_account_number', $teacher->bank_account_number) }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" name="ifsc_code" class="form-control" 
                                           value="{{ old('ifsc_code', $teacher->ifsc_code) }}">
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">Experience Details</label>
                                    <textarea name="experience_details" class="form-control" rows="3">{{ old('experience_details', $teacher->experience_details) }}</textarea>
                                    <small class="text-muted">List previous schools/colleges with dates</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="border-top pt-4 mt-4">
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    💾 Update Teacher
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg ms-2">
                                    🔄 Reset Changes
                                </button>
                            </div>
                            
                            <!-- Danger Zone -->
                            <div>
                                <form action="{{ route('admin.teachers.destroy', $teacher->id) }}" 
                                      method="POST" 
                                      class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to delete this teacher? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        🗑️ Delete Teacher
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image Preview Function
        function previewImage(event) {
            const reader = new FileReader();
            const preview = document.getElementById('profilePreview');
            
            reader.onload = function() {
                if (preview.tagName === 'IMG') {
                    preview.src = reader.result;
                } else {
                    // Convert div to img
                    const img = document.createElement('img');
                    img.id = 'profilePreview';
                    img.className = 'profile-preview';
                    img.src = reader.result;
                    preview.parentNode.replaceChild(img, preview);
                }
            }
            
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
            });
        });
        
        // Exam Head Toggle Function
        function toggleExamHead(isChecked) {
            if (confirm('Are you sure you want to ' + (isChecked ? 'assign' : 'remove') + ' exam head status for this teacher?')) {
                // Submit the form to toggle exam head status
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('_method', 'POST');
                formData.append('make_exam_head', isChecked ? '1' : '0');
                
                fetch('{{ route("admin.teachers.toggle-exam-head", $teacher->id) }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert(data.message || 'Exam head status updated successfully!');
                        // Reload page to update UI
                        window.location.reload();
                    } else {
                        alert('Error updating exam head status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating exam head status');
                });
            } else {
                // Revert the checkbox if user cancels
                document.getElementById('examHeadToggle').checked = !isChecked;
            }
        }
    </script>
</body>
</html>