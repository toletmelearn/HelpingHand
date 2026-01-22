<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Bell Timing - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-plus-circle"></i> Create Bell Timing</h1>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Schedule Details</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('bell-timing.store') }}" method="POST">
                            @csrf
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="day_of_week" class="form-label">Day of Week *</label>
                                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                                            <option value="">Select Day</option>
                                            @foreach($daysOfWeek as $day)
                                                <option value="{{ $day }}">{{ $day }}</option>
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
                                                <option value="{{ $section }}">{{ $section }}</option>
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
                                               placeholder="e.g., Period 1, Lunch, Break" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="custom_label" class="form-label">Custom Label</label>
                                        <input type="text" class="form-control" id="custom_label" name="custom_label" 
                                               placeholder="e.g., Math Period, Science Lab">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Start Time *</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">End Time *</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="order_index" class="form-label">Order Index *</label>
                                        <input type="number" class="form-control" id="order_index" name="order_index" 
                                               min="0" value="0" required>
                                        <div class="form-text">Lower number comes first</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year</label>
                                        <select class="form-select" id="academic_year" name="academic_year">
                                            <option value="">Select Year</option>
                                            @foreach($academicYears as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" name="semester">
                                            <option value="">Select Semester</option>
                                            <option value="First">First</option>
                                            <option value="Second">Second</option>
                                            <option value="Third">Third</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="is_active">Active Schedule</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_break" name="is_break" value="1">
                                        <label class="form-check-label" for="is_break">Is Break Time</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="color_code" class="form-label">Color Code</label>
                                        <input type="color" class="form-control form-control-color" id="color_code" name="color_code" 
                                               value="#007bff" title="Choose color">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Schedule
                                </button>
                                <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
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
                        <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>Ensure no time conflicts with existing schedules</li>
                            <li>Set lower order index for earlier periods</li>
                            <li>Use "Is Break Time" for lunch and recess periods</li>
                            <li>Color codes help visually distinguish periods</li>
                            <li>Leave "Class/Section" blank for general schedules</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Common Period Names</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Period 1')">Period 1</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Period 2')">Period 2</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Period 3')">Period 3</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Lunch Break')">Lunch Break</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Morning Assembly')">Morning Assembly</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('Evening Break')">Evening Break</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setPeriod(name) {
            document.getElementById('period_name').value = name;
        }
    </script>
</body>
</html>