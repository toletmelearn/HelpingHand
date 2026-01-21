<!DOCTYPE html>
<html>
<head>
    <title>{{ $teacher->name }} - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>👨‍🏫 Teacher Details</h2>
    <a href="/teachers" class="btn btn-secondary mb-3">← Back to List</a>
    <div class="card">
        <div class="card-body">
            @if(isset($teacher))
                <p><strong>Name:</strong> {{ $teacher->name }}</p>
                <p><strong>Email:</strong> {{ $teacher->email }}</p>
                <p><strong>Phone:</strong> {{ $teacher->phone }}</p>
                <p><strong>Qualification:</strong> {{ $teacher->qualification }}</p>
                <p><strong>Subject:</strong> {{ $teacher->subject_specialization }}</p>
            @else
                <p class="text-danger">Teacher not found!</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>