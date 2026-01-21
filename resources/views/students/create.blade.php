<!-- resources/views/students/create.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelpingHand - Add Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3>➕ Add New Student</h3>
                    </div>
                    
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                ✅ {{ session('success') }}
                            </div>
                        @endif

                        <form action="{{ route('students.store') }}" method="POST">
                            @csrf <!-- Security token -->
                            
                            <div class="row">
                                <!-- Student Name -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student Name *</label>
                                    <input type="text" name="name" class="form-control" required 
                                           placeholder="Enter full name">
                                </div>

                                <!-- Father's Name -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Father's Name *</label>
                                    <input type="text" name="father_name" class="form-control" required 
                                           placeholder="Enter father's name">
                                </div>

                                <!-- Mother's Name -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mother's Name *</label>
                                    <input type="text" name="mother_name" class="form-control" required 
                                           placeholder="Enter mother's name">
                                </div>

                                <!-- Date of Birth -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth *</label>
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                </div>

                                <!-- Aadhar Number (Feature #3) -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Aadhar Number *</label>
                                    <input type="text" name="aadhar_number" class="form-control" required 
                                           placeholder="12-digit number" maxlength="12">
                                    <small class="text-muted">We'll verify this automatically</small>
                                </div>

                                <!-- Phone -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" name="phone" class="form-control" required 
                                           placeholder="10-digit number" maxlength="10">
                                </div>

                                <!-- Address -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address *</label>
                                    <textarea name="address" class="form-control" rows="3" required 
                                              placeholder="Full address"></textarea>
                                </div>
                            </div>
<!-- New Categorization Fields -->
<div class="row mt-3">
    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">🎓 Academic Details</h5>
        
        <div class="mb-3">
            <label class="form-label required">Class</label>
            <select name="class" class="form-select" required>
                <option value="">Select Class</option>
                <option value="Nursery">Nursery</option>
                <option value="LKG">LKG</option>
                <option value="UKG">UKG</option>
                @for($i = 1; $i <= 12; $i++)
                    <option value="Class {{ $i }}">Class {{ $i }}</option>
                @endfor
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Section</label>
            <select name="section" class="form-select">
                <option value="">Select Section</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Roll Number</label>
            <input type="number" name="roll_number" class="form-control" min="1">
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <h5 class="border-bottom pb-2 mb-3">👤 Personal Details</h5>
        
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
            <label class="form-label required">Category</label>
            <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <option value="General">General</option>
                <option value="OBC">OBC</option>
                <option value="SC">SC</option>
                <option value="ST">ST</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Religion</label>
            <input type="text" name="religion" class="form-control" placeholder="e.g., Hindu, Muslim, Christian">
        </div>

        <div class="mb-3">
            <label class="form-label">Caste</label>
            <input type="text" name="caste" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Blood Group</label>
            <select name="blood_group" class="form-select">
                <option value="">Select Blood Group</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
            </select>
        </div>
    </div>
</div>
                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    💾 Save Student
                                </button>
                                <a href="/" class="btn btn-secondary btn-lg ms-2">
                                    ← Back to Home
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>