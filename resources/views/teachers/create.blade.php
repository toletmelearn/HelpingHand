<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .required:after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>👨‍🏫 Add New Teacher</h2>
            <a href="{{ url('/admin/teachers') }}" class="btn btn-secondary">
                ← Back to Teachers
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                ✅ {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow">
            <div class="card-body">
                @if ($errors->any())
    <div style="color: red; margin-bottom: 10px;">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

                <form action="{{ route('admin.teachers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <!-- Personal Details -->
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">👤 Personal Details</h5>
                            
                            <div class="mb-3">
                                <label class="form-label required">Full Name</label>
                                <input type="text" name="name" class="form-control" 
                                       value="{{ old('name') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="{{ old('email') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="{{ old('phone') }}" maxlength="10" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Aadhar Number</label>
                                <input type="text" name="aadhar_number" class="form-control"
                                       value="{{ old('aadhar_number') }}" maxlength="12" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Relative Name (Father's / Husband's / Wife's Name)</label>
                                <input type="text" name="relative_name" class="form-control"
                                       value="{{ old('relative_name') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Emergency Contact Number</label>
                                <input type="tel" name="emergency_contact" class="form-control"
                                       value="{{ old('emergency_contact') }}" maxlength="10">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" name="profile_image" class="form-control"
                                       accept="image/*">
                            </div>
                        </div>

                        <!-- Professional Details -->
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">📚 Professional Details</h5>

                            <div class="mb-3">
                                <label class="form-label required">Qualification</label>
                                <select name="qualification" class="form-select" required>
                                    <option value="">Select Qualification</option>
                                    @foreach($qualifications as $qualification)
                                        <option value="{{ $qualification }}"
                                            {{ old('qualification') == $qualification ? 'selected' : '' }}>
                                            {{ $qualification }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Educational Qualification (B.Ed with Subjects / Other)</label>
                                <input type="text" name="educational_qualification" class="form-control"
                                       value="{{ old('educational_qualification') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Subject Specialization</label>
                                <select name="subject_specialization" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}"
                                            {{ old('subject_specialization') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Date of Joining</label>
                                <input type="date" name="date_of_joining" class="form-control" 
                                       value="{{ old('date_of_joining') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Monthly Salary (₹)</label>
                                <input type="number" name="salary" class="form-control" 
                                       value="{{ old('salary') }}" step="0.01" min="0" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="on_leave" {{ old('status') == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                                </select>
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div class="col-12 mt-3">
                            <h5 class="border-bottom pb-2 mb-3">📝 Additional Details</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Address for Correspondence</label>
                                    <textarea name="address" class="form-control" rows="3" required>{{ old('address') }}</textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Permanent Address</label>
                                    <textarea name="permanent_address" class="form-control" rows="3">{{ old('permanent_address') }}</textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bank Account Number</label>
                                    <input type="text" name="bank_account_number" class="form-control"
                                           value="{{ old('bank_account_number') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" name="ifsc_code" class="form-control"
                                           value="{{ old('ifsc_code') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Experience Details</label>
                                    <textarea name="experience_details" class="form-control" rows="3" required>{{ old('experience_details') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HR Master Data -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="border-bottom pb-2 mb-3">🗂️ HR Master Data</h5>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Classes Taught with Subjects</label>
                            <textarea name="classes_taught" class="form-control" rows="2"
                                      placeholder="e.g., Class 6-A Maths, Class 7-B Science">{{ old('classes_taught') }}</textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">No. of Periods</label>
                            <input type="number" name="no_of_periods" class="form-control"
                                   value="{{ old('no_of_periods') }}" min="0" max="60">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Class &amp; Section</label>
                            <input type="text" name="class_section" class="form-control"
                                   value="{{ old('class_section') }}" placeholder="e.g., 8-B">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Responsibilities</label>
                            <textarea name="responsibilities" class="form-control" rows="2">{{ old('responsibilities') }}</textarea>
                        </div>
                    </div>
<!-- ========== ADD THIS CODE TO CREATE FORM ========== -->

<!-- New Categorization Fields -->
<div class="row mt-4">
    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">🏫 Teaching Details</h5>
        
        <div class="mb-3">
            <label class="form-label required">Wing</label>
            <select name="wing" class="form-select" required>
                <option value="">Select Wing</option>
                <option value="primary">Primary Wing (Nursery - Class 2)</option>
                <option value="junior">Junior Wing (Class 3 - Class 5)</option>
                <option value="senior">Senior Wing (Class 6 - Class 12)</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label required">Teacher Type</label>
            <select name="teacher_type" class="form-select" required>
                <option value="">Select Type</option>
                <option value="PRT">PRT (Primary Teacher)</option>
                <option value="TGT">TGT (Trained Graduate Teacher)</option>
                <option value="PGT">PGT (Post Graduate Teacher)</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Subjects (Can teach multiple)</label>
            <select name="subjects[]" class="form-select" multiple>
                <option value="Mathematics">Mathematics</option>
                <option value="Science">Science</option>
                <option value="English">English</option>
                <option value="Hindi">Hindi</option>
                <option value="Social Studies">Social Studies</option>
                <option value="Physics">Physics</option>
                <option value="Chemistry">Chemistry</option>
                <option value="Biology">Biology</option>
                <option value="Computer Science">Computer Science</option>
                <option value="Physical Education">Physical Education</option>
                <option value="Arts">Arts</option>
                <option value="Music">Music</option>
                <option value="Dance">Dance</option>
            </select>
            <small class="text-muted">Hold Ctrl to select multiple subjects</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Designation</label>
            <input type="text" name="designation" class="form-control" 
                   value="Teacher" placeholder="e.g., Teacher, HOD, Principal">
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">👤 Personal & Professional</h5>
        
        <div class="mb-3">
            <label class="form-label required">Gender</label>
            <select name="gender" class="form-select" required>
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label required">Employment Type</label>
            <select name="employment_type" class="form-select" required>
                <option value="permanent">Permanent</option>
                <option value="contract">Contract</option>
                <option value="temporary">Temporary</option>
                <option value="guest">Guest Faculty</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Employee ID</label>
            <input type="text" name="employee_id" class="form-control" 
                   placeholder="SCH/TCH/2024/001">
        </div>

        <div class="mb-3">
            <label class="form-label">UAN Number (PF)</label>
            <input type="text" name="uan_number" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">PAN Number</label>
            <input type="text" name="pan_number" class="form-control">
        </div>
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
                       {{ old('is_exam_head') ? 'checked' : '' }}>
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
                       {{ old('is_exam_cell_member') ? 'checked' : '' }}>
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

<!-- ========== END OF ADDED CODE ========== -->
                    <!-- Submit Buttons -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-success btn-lg px-5">
                            💾 Save Teacher
                        </button>
                        <button type="reset" class="btn btn-outline-secondary btn-lg ms-2">
                            🔄 Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation feedback
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.checkValidity()) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            });
        });
    </script>
</body>
</html>