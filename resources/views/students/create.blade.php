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