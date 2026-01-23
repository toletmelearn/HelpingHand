<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bell Timing - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-pencil-square"></i> Edit Bell Timing</h1>
            <div>
                <a href="{{ route('bell-timing.show', $bellTiming) }}" class="btn btn-secondary">
                    <i class="bi bi-eye"></i> View Details
                </a>
                <a href="{{ route('bell-timing.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Please fix the following errors:
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Schedule Details</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('bell-timing.update', $bellTiming) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="day_of_week" class="form-label">Day of Week *</label>
                                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                                            <option value="">Select Day</option>
                                            @foreach($daysOfWeek as $day)
                                                <option value="{{ $day }}" {{ old('day_of_week', $bellTiming->day_of_week) == $day ? 'selected' : '' }}>
                                                    {{ $day }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="class_section" class="form-label">Class/Section</label>
                                        <select class="form-select" id="class_section" name="class_section">
                                            <option value="">All Classes</option>
                                            @foreach($classSections as $section)
                                                <option value="{{ $section }}" {{ old('class_section', $bellTiming->class_section) == $section ? 'selected' : '' }}>
                                                    {{ $section }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="period_name" class="form-label">Period Name *</label>
                                        <input type="text" class="form-control" id="period_name" name="period_name" 
                                               value="{{ old('period_name', $bellTiming->period_name) }}"
                                               placeholder="e.g., Period 1, Lunch, Break" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="custom_label" class="form-label">Custom Label</label>
                                        <input type="text" class="form-control" id="custom_label" name="custom_label" 
                                               value="{{ old('custom_label', $bellTiming->custom_label) }}"
                                               placeholder="e.g., Math Period, Science Lab">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Start Time *</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time" 
                                               value="{{ old('start_time', $bellTiming->start_time->format('H:i')) }}" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">End Time *</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" 
                                               value="{{ old('end_time', $bellTiming->end_time->format('H:i')) }}" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="order_index" class="form-label">Order Index *</label>
                                        <input type="number" class="form-control" id="order_index" name="order_index" 
                                               min="0" value="{{ old('order_index', $bellTiming->order_index) }}" required>
                                        <div class="form-text">Lower number comes first</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year</label>
                                        <select class="form-select" id="academic_year" name="academic_year">
                                            <option value="">Select Year</option>
                                            @foreach($academicYears as $year)
                                                <option value="{{ $year }}" {{ old('academic_year', $bellTiming->academic_year) == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" name="semester">
                                            <option value="">Select Semester</option>
                                            <option value="First" {{ old('semester', $bellTiming->semester) == 'First' ? 'selected' : '' }}>First</option>
                                            <option value="Second" {{ old('semester', $bellTiming->semester) == 'Second' ? 'selected' : '' }}>Second</option>
                                            <option value="Third" {{ old('semester', $bellTiming->semester) == 'Third' ? 'selected' : '' }}>Third</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" 
                                               {{ old('is_active', $bellTiming->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">Active Schedule</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_break" name="is_break" value="1"
                                               {{ old('is_break', $bellTiming->is_break) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_break">Is Break Time</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="color_code" class="form-label">Color Code</label>
                                        <input type="color" class="form-control form-control-color" id="color_code" name="color_code" 
                                               value="{{ old('color_code', $bellTiming->color_code ?? '#007bff') }}" title="Choose color">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Schedule
                                </button>
                                <a href="{{ route('bell-timing.show', $bellTiming) }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Current Schedule Info</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Period:</strong> {{ $bellTiming->period_name }}</p>
                        <p><strong>Day:</strong> {{ $bellTiming->day_of_week }}</p>
                        <p><strong>Time:</strong> {{ $bellTiming->start_time->format('h:i A') }} - {{ $bellTiming->end_time->format('h:i A') }}</p>
                        <p><strong>Status:</strong> 
                            @if($bellTiming->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </p>
                        @if($bellTiming->class_section)
                            <p><strong>Class:</strong> {{ $bellTiming->class_section }}</p>
                        @endif
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>Ensure no time conflicts with existing schedules</li>
                            <li>Changing times may affect student schedules</li>
                            <li>Consider notifying affected classes/students</li>
                            <li>Test the updated schedule thoroughly</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>