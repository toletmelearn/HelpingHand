<!DOCTYPE html>
<html>
<head>
    <title>Edit Student - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>✏️ Edit Student</h2>
    <a href="/students" class="btn btn-secondary mb-3">← Back to List</a>
    
    @if(isset($student))
    <form action="{{ route('students.update', $student->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="{{ $student->name }}" required>
        </div>
        
        <div class="mb-3">
            <label>Father's Name</label>
            <input type="text" name="father_name" class="form-control" value="{{ $student->father_name }}" required>
        </div>
        
        <div class="mb-3">
            <label>Mother's Name</label>
            <input type="text" name="mother_name" class="form-control" value="{{ $student->mother_name }}" required>
        </div>
        
        <div class="mb-3">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="{{ $student->date_of_birth }}" required>
        </div>
        
        <div class="mb-3">
            <label>Aadhar Number</label>
            <input type="text" name="aadhar_number" class="form-control" value="{{ $student->aadhar_number }}" maxlength="12" required>
        </div>
        
        <div class="mb-3">
            <label>Phone</label>
            <input type="tel" name="phone" class="form-control" value="{{ $student->phone }}" maxlength="10" required>
        </div>
        
        <div class="mb-3">
            <label>Address</label>
            <textarea name="address" class="form-control" rows="3" required>{{ $student->address }}</textarea>
        </div>
        
        <button type="submit" class="btn btn-success">💾 Update Student</button>
    </form>
    @else
    <div class="alert alert-danger">Student not found!</div>
    @endif
</div>
</body>
</html>