<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-pencil"></i> Edit Attendance</h1>
            <div>
                <a href="{{ route('attendance.show', $attendance) }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Details
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark"></i> Attendance Details</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('attendance.update', $attendance) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="text" id="date" class="form-control"
                                               value="{{ $attendance->date->format('Y-m-d') }}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="class" class="form-label">Class</label>
                                        <input type="text" id="class" class="form-control"
                                               value="{{ $attendance->class }}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                            <option value="present" {{ old('status', $attendance->status) == 'present' ? 'selected' : '' }}>
                                                Present
                                            </option>
                                            <option value="absent" {{ old('status', $attendance->status) == 'absent' ? 'selected' : '' }}>
                                                Absent
                                            </option>
                                            <option value="late" {{ old('status', $attendance->status) == 'late' ? 'selected' : '' }}>
                                                Late
                                            </option>
                                            <option value="half_day" {{ old('status', $attendance->status) == 'half_day' ? 'selected' : '' }}>
                                                Half Day
                                            </option>
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="student_name" class="form-label">Student</label>
                                        <input type="text" class="form-control" value="{{ $attendance->student->name ?? 'N/A' }}" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <select name="subject" id="subject" class="form-select @error('subject') is-invalid @enderror" required>
                                            <option value="">Select Subject</option>
                                            @foreach($subjects as $subject)
                                                <option value="{{ $subject }}" {{ old('subject', $attendance->subject) == $subject ? 'selected' : '' }}>
                                                    {{ $subject }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('subject')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="period" class="form-label">Period</label>
                                        <input type="text" id="period" class="form-control"
                                               value="{{ $attendance->period }}" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea name="remarks" id="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks', $attendance->remarks) }}</textarea>
                                @error('remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ route('attendance.show', $attendance) }}" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-x"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check"></i> Update Attendance
                                </button>
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
