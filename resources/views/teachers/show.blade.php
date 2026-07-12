<!DOCTYPE html>
<html>
<head>
    <title>{{ $teacher->name }} - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>👨‍🏫 Teacher Details</h2>
    <a href="{{ route('admin.teachers.index') }}" class="btn btn-secondary mb-3">← Back to List</a>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            @if(isset($teacher))
                <div class="text-center mb-3">
                    <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->name }}"
                         class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                </div>
                @if(\App\Helpers\FieldPermissionHelper::canEditField('teacher', 'profile_image'))
                <form action="{{ route('teachers.photo.update', $teacher->id) }}" method="POST"
                      enctype="multipart/form-data" class="text-center mb-3">
                    @csrf
                    <input type="file" name="photo" class="form-control form-control-sm d-inline-block w-auto mb-2"
                           accept="image/jpeg,image/png,image/gif" required>
                    <button type="submit" class="btn btn-sm btn-primary">
                        📷 Change Photo
                    </button>
                </form>
                @endif
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