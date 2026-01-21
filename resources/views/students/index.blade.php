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
    <!-- Simple Bulk Operations Card -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">📦 Bulk Operations (Simple)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Export CSV -->
            <div class="col-md-4 mb-3">
                <div class="card border-success h-100">
                    <div class="card-body text-center">
                        <h6>📤 Export CSV</h6>
                        <p class="small text-muted">Download all students as CSV file</p>
                        <a href="{{ route('students.export.csv') }}" class="btn btn-success">
                            <i class="bi bi-file-earmark-arrow-down"></i> Download CSV
                        </a>
                        <small class="d-block mt-2 text-muted">Can be opened in Excel</small>
                    </div>
                </div>
            </div>
            
            <!-- Import CSV -->
            <div class="col-md-4 mb-3">
                <div class="card border-info h-100">
                    <div class="card-body text-center">
                        <h6>📥 Import CSV</h6>
                        <p class="small text-muted">Upload CSV file to add multiple students</p>
                        <form action="{{ route('students.import.csv') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                                <button type="submit" class="btn btn-info btn-sm">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            </div>
                            <small class="d-block mt-2">
                                <a href="#" onclick="downloadSampleCSV()" class="text-decoration-none">
                                    <i class="bi bi-download"></i> Download sample CSV
                                </a>
                            </small>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Update -->
            <div class="col-md-4 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-body text-center">
                        <h6>⚡ Quick Actions</h6>
                        <p class="small text-muted">Update multiple students at once</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-warning btn-sm" onclick="bulkPromoteClass()">
                                <i class="bi bi-arrow-up"></i> Promote to Next Class
                            </button>
                            <button class="btn btn-outline-primary btn-sm" onclick="bulkUpdateSection()">
                                <i class="bi bi-arrow-right"></i> Change Section
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for bulk operations -->
<script>
function downloadSampleCSV() {
    // Create sample CSV content
    const csvContent = "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n" +
                      "1,Sample Student,Sample Father,Sample Mother,2010-01-01,123456789012,9876543210,male,General,Class 5,A,1,Hindu,General,A+,Sample Address";
    
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "sample-students.csv";
    link.click();
}

function bulkPromoteClass() {
    const selectedIds = getSelectedStudentIds();
    if (selectedIds.length === 0) {
        alert('Please select students first!');
        return;
    }
    
    if (confirm(`Promote ${selectedIds.length} students to next class?`)) {
        // Implement AJAX call here
        alert('Promotion feature will be implemented');
    }
}

function bulkUpdateSection() {
    const selectedIds = getSelectedStudentIds();
    if (selectedIds.length === 0) {
        alert('Please select students first!');
        return;
    }
    
    const newSection = prompt('Enter new section (A, B, C, etc.):', 'A');
    if (newSection) {
        // Implement AJAX call here
        alert(`Will update ${selectedIds.length} students to section ${newSection}`);
    }
}

function getSelectedStudentIds() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}
</script>
</body>
</html>