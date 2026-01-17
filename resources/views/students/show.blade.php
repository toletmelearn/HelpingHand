<!DOCTYPE html>
<html>
<head>
    <title>View Student - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>👨‍🎓 Student Details</h2>
    <a href="/students" class="btn btn-secondary mb-3">← Back to List</a>
    <div class="card">
        <div class="card-body">
            @if(isset($student))
                <p><strong>Name:</strong> {{ $student->name }}</p>
                <p><strong>Father:</strong> {{ $student->father_name }}</p>
                <p><strong>Mother:</strong> {{ $student->mother_name }}</p>
                <p><strong>Date of Birth:</strong> {{ $student->date_of_birth }}</p>
                <p><strong>Aadhar:</strong> {{ $student->aadhar_number }}</p>
                <p><strong>Phone:</strong> {{ $student->phone }}</p>
                <p><strong>Address:</strong> {{ $student->address }}</p>
            @else
                <p class="text-danger">Student not found!</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>