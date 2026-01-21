<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .attendance-card {
            transition: all 0.3s ease;
        }
        .attendance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .student-row {
            cursor: pointer;
        }
        .status-present { background-color: #d1f2eb !important; }
        .status-absent { background-color: #fadbd8 !important; }
        .status-late { background-color: #fef9e7 !important; }
        .status-half_day { background-color: #ebf5fb !important; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-calendar-check"></i> Mark Daily Attendance</h1>
            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <!-- Attendance Info -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Attendance Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="fw-bold">Date:</label>
                        <p>{{ \Carbon\Carbon::parse($date)->format('d F Y') }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Class:</label>
                        <p>{{ $class }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Total Students:</label>
                        <p>{{ $students->count() }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Session:</label>
                        <p>{{ date('Y') . '-' . (date('Y') + 1) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Form -->
        <form action="{{ route('attendance.store') }}" method="POST">
            @csrf
            
            <input type="hidden" name="class" value="{{ $class }}">
            <input type="hidden" name="date" value="{{ $date }}">
            
            <!-- Subject and Period Selection -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-book"></i> Subject & Period</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="subject" class="form-label">Subject *</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="">Select Subject</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject }}">{{ $subject }}</option>
                                @endforeach
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="period" class="form-label">Period</label>
                            <select class="form-select" id="period" name="period">
                                <option value="">Full Day</option>
                                <option value="Period 1">Period 1</option>
                                <option value="Period 2">Period 2</option>
                                <option value="Period 3">Period 3</option>
                                <option value="Period 4">Period 4</option>
                                <option value="Period 5">Period 5</option>
                                <option value="Period 6">Period 6</option>
                                <option value="Period 7">Period 7</option>
                                <option value="Period 8">Period 8</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" onclick="markAll('present')">
                            <i class="bi bi-check-circle"></i> Mark All Present
                        </button>
                        <button type="button" class="btn btn-danger" onclick="markAll('absent')">
                            <i class="bi bi-x-circle"></i> Mark All Absent
                        </button>
                        <button type="button" class="btn btn-warning" onclick="markAll('late')">
                            <i class="bi bi-clock"></i> Mark All Late
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearAll()">
                            <i class="bi bi-eraser"></i> Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Students List -->
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Students List</h5>
                    <div>
                        <span class="badge bg-success me-2">Present: <span id="presentCount">0</span></span>
                        <span class="badge bg-danger me-2">Absent: <span id="absentCount">0</span></span>
                        <span class="badge bg-warning me-2">Late: <span id="lateCount">0</span></span>
                        <span class="badge bg-secondary">Half Day: <span id="halfDayCount">0</span></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="25%">Student Name</th>
                                <th width="10%">Roll No</th>
                                <th width="15%">Present</th>
                                <th width="15%">Absent</th>
                                <th width="15%">Late</th>
                                <th width="15%">Half Day</th>
                                <th width="20%">Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceBody">
                            @foreach($students as $index => $student)
                            <tr class="student-row" id="row-{{ $student->id }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $student->name }}</strong>
                                    <br><small class="text-muted">{{ $student->father_name }}</small>
                                </td>
                                <td>{{ $student->roll_number }}</td>
                                <td>
                                    <input type="radio" name="statuses[{{ $index }}]" value="present" 
                                           class="btn-check" id="present_{{ $student->id }}"
                                           onchange="updateRowStyle({{ $student->id }}, 'present')">
                                    <label class="btn btn-outline-success btn-sm" for="present_{{ $student->id }}">
                                        <i class="bi bi-check"></i> Present
                                    </label>
                                </td>
                                <td>
                                    <input type="radio" name="statuses[{{ $index }}]" value="absent" 
                                           class="btn-check" id="absent_{{ $student->id }}"
                                           onchange="updateRowStyle({{ $student->id }}, 'absent')" checked>
                                    <label class="btn btn-outline-danger btn-sm" for="absent_{{ $student->id }}">
                                        <i class="bi bi-x"></i> Absent
                                    </label>
                                </td>
                                <td>
                                    <input type="radio" name="statuses[{{ $index }}]" value="late" 
                                           class="btn-check" id="late_{{ $student->id }}"
                                           onchange="updateRowStyle({{ $student->id }}, 'late')">
                                    <label class="btn btn-outline-warning btn-sm" for="late_{{ $student->id }}">
                                        <i class="bi bi-clock"></i> Late
                                    </label>
                                </td>
                                <td>
                                    <input type="radio" name="statuses[{{ $index }}]" value="half_day" 
                                           class="btn-check" id="half_day_{{ $student->id }}"
                                           onchange="updateRowStyle({{ $student->id }}, 'half_day')">
                                    <label class="btn btn-outline-secondary btn-sm" for="half_day_{{ $student->id }}">
                                        <i class="bi bi-sun"></i> Half Day
                                    </label>
                                </td>
                                <td>
                                    <input type="text" name="remarks[{{ $index }}]" 
                                           class="form-control form-control-sm" 
                                           placeholder="Remarks (optional)">
                                    <input type="hidden" name="student_ids[{{ $index }}]" value="{{ $student->id }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Save Attendance
                    </button>
                    <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update row style based on selection
        function updateRowStyle(studentId, status) {
            const row = document.getElementById('row-' + studentId);
            row.className = 'student-row ' + (status ? 'status-' + status : '');
            updateCounts();
        }

        // Mark all students with same status
        function markAll(status) {
            const radios = document.querySelectorAll('input[type="radio"][value="' + status + '"]');
            radios.forEach(radio => {
                radio.checked = true;
                const studentId = radio.id.split('_')[1];
                updateRowStyle(studentId, status);
            });
        }

        // Clear all selections
        function clearAll() {
            const allRadios = document.querySelectorAll('input[type="radio"]');
            allRadios.forEach(radio => {
                radio.checked = false;
            });
            
            document.querySelectorAll('.student-row').forEach(row => {
                row.className = 'student-row';
            });
            
            updateCounts();
        }

        // Update counts display
        function updateCounts() {
            const presentCount = document.querySelectorAll('input[type="radio"][value="present"]:checked').length;
            const absentCount = document.querySelectorAll('input[type="radio"][value="absent"]:checked').length;
            const lateCount = document.querySelectorAll('input[type="radio"][value="late"]:checked').length;
            const halfDayCount = document.querySelectorAll('input[type="radio"][value="half_day"]:checked').length;
            
            document.getElementById('presentCount').textContent = presentCount;
            document.getElementById('absentCount').textContent = absentCount;
            document.getElementById('lateCount').textContent = lateCount;
            document.getElementById('halfDayCount').textContent = halfDayCount;
        }

        // Initialize counts on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCounts();
        });
    </script>
</body>
</html>