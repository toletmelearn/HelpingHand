<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelpingHand - All Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>👨‍🎓 Students List</h1>
            <a href="{{ route('students.create') }}" class="btn btn-primary">
                ➕ Add New Student
            </a>
        </div>

        @if($students->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Father's Name</th>
                            <th>Date of Birth</th>
                            <th>Aadhar</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                        <tr>
                            <td>{{ $student->id }}</td>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->father_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($student->date_of_birth)->format('d/m/Y') }}</td>
                            <td>{{ substr($student->aadhar_number, 0, 4) }}****{{ substr($student->aadhar_number, -4) }}</td>
                            <td>{{ $student->phone }}</td>
                            <td>
    <!-- View Button -->
    <a href="{{ url('/students/' . $student->id) }}" 
       class="btn btn-sm btn-info">
       👁️ View
    </a>
    
    <!-- Edit Button -->
    <a href="{{ url('/students/' . $student->id . '/edit') }}" 
       class="btn btn-sm btn-warning">
       ✏️ Edit
    </a>
    
    <!-- Delete Button (Optional) -->
    <form action="{{ url('/students/' . $student->id) }}" 
          method="POST" 
          style="display:inline;"
          onsubmit="return confirm('Are you sure you want to delete this student?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger mt-1">
            🗑️ Delete
        </button>
    </form>
</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Count display -->
            <div class="alert alert-info">
                📊 Total Students: <strong>{{ $students->count() }}</strong>
            </div>
        @else
            <div class="alert alert-warning text-center">
                📭 No students found. 
                <a href="{{ route('students.create') }}">Add your first student!</a>
            </div>
        @endif
    </div>
</body>
</html>