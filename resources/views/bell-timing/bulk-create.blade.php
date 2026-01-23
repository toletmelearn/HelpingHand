<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Create Bell Timings - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .period-row {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }
        .day-selector {
            cursor: pointer;
            transition: all 0.2s;
        }
        .day-selector:hover {
            transform: scale(1.05);
        }
        .day-selector.selected {
            background-color: #007bff;
            color: white;
            border-color: #007bff !important;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-file-earmark-plus"></i> Bulk Create Bell Timings</h1>
            <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('errors'))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Some errors occurred:
                <ul class="mb-0 mt-2">
                    @foreach(session('errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
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

        <form action="{{ route('bell-timing.bulk-create') }}" method="POST" id="bulkForm">
            @csrf
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Days Selection -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Select Days</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($daysOfWeek as $day)
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check day-selector border p-3 rounded" 
                                             onclick="toggleDay(this, '{{ $day }}')">
                                            <input type="checkbox" class="form-check-input d-none" 
                                                   name="days[]" value="{{ $day }}" id="day_{{ $day }}">
                                            <label class="form-check-label fw-bold mb-0" for="day_{{ $day }}">
                                                <i class="bi bi-calendar-day"></i> {{ $day }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllDays()">
                                    <i class="bi bi-check-all"></i> Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllDays()">
                                    <i class="bi bi-x-circle"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Class and Academic Year -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Class Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="class_section" class="form-label">Class/Section *</label>
                                        <select class="form-select" id="class_section" name="class_section" required>
                                            <option value="">Select Class</option>
                                            @foreach($classSections as $section)
                                                <option value="{{ $section }}">{{ $section }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year *</label>
                                        <select class="form-select" id="academic_year" name="academic_year" required>
                                            <option value="">Select Year</option>
                                            @foreach($academicYears as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
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

                    <!-- Periods Configuration -->
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ol"></i> Configure Periods</h5>
                            <button type="button" class="btn btn-light btn-sm" onclick="addPeriod()">
                                <i class="bi bi-plus-circle"></i> Add Period
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="periodsContainer">
                                <!-- Period rows will be added here dynamically -->
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addCommonPeriods()">
                                    <i class="bi bi-magic"></i> Add Common Schedule
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearAllPeriods()">
                                    <i class="bi bi-trash"></i> Clear All Periods
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Instructions</h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Select the days you want to create schedules for</li>
                                <li>Choose the class/section and academic year</li>
                                <li>Add periods with their start/end times</li>
                                <li>Mark break periods if applicable</li>
                                <li>Review all settings before submitting</li>
                            </ol>
                            <hr>
                            <h6><i class="bi bi-exclamation-triangle"></i> Important Notes:</h6>
                            <ul>
                                <li>All selected days will get the same period structure</li>
                                <li>Time conflicts will be checked automatically</li>
                                <li>Existing schedules will not be overwritten</li>
                                <li>You can edit individual schedules after creation</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Review & Submit</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Selected Days:</label>
                                <div id="selectedDaysPreview" class="small text-muted">None selected</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Periods to Create:</label>
                                <div id="periodsCountPreview" class="small text-muted">0 periods</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Total Schedules:</label>
                                <div id="totalSchedulesPreview" class="small text-muted">0 schedules</div>
                            </div>
                            <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>
                                <i class="bi bi-save"></i> Create All Schedules
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let periodCounter = 0;

        function toggleDay(element, dayValue) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            const isSelected = checkbox.checked;
            
            checkbox.checked = !isSelected;
            element.classList.toggle('selected', !isSelected);
            
            updatePreview();
        }

        function selectAllDays() {
            document.querySelectorAll('.day-selector').forEach(element => {
                const checkbox = element.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
                element.classList.add('selected');
            });
            updatePreview();
        }

        function clearAllDays() {
            document.querySelectorAll('.day-selector').forEach(element => {
                const checkbox = element.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
                element.classList.remove('selected');
            });
            updatePreview();
        }

        function addPeriod() {
            periodCounter++;
            const container = document.getElementById('periodsContainer');
            
            const periodHtml = `
                <div class="period-row" id="period_${periodCounter}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label">Period Name *</label>
                                <input type="text" class="form-control" name="periods[${periodCounter}][period_name]" 
                                       placeholder="e.g., Period 1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="periods[${periodCounter}][start_time]" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="periods[${periodCounter}][end_time]" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Order</label>
                                <input type="number" class="form-control" name="periods[${periodCounter}][order_index]" 
                                       value="${periodCounter}" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="periods[${periodCounter}][is_break]">
                                    <option value="0">Class</option>
                                    <option value="1">Break</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod(${periodCounter})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label">Custom Label</label>
                                <input type="text" class="form-control" name="periods[${periodCounter}][custom_label]" 
                                       placeholder="e.g., Math Period">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label">Color Code</label>
                                <input type="color" class="form-control form-control-color" 
                                       name="periods[${periodCounter}][color_code]" value="#007bff">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', periodHtml);
            updatePreview();
        }

        function removePeriod(id) {
            document.getElementById(`period_${id}`).remove();
            updatePreview();
        }

        function addCommonPeriods() {
            // Clear existing periods
            document.getElementById('periodsContainer').innerHTML = '';
            periodCounter = 0;
            
            // Add common school periods
            const commonPeriods = [
                {name: 'Morning Assembly', start: '08:00', end: '08:30', break: 1},
                {name: 'Period 1', start: '08:30', end: '09:20', break: 0},
                {name: 'Period 2', start: '09:20', end: '10:10', break: 0},
                {name: 'Break', start: '10:10', end: '10:30', break: 1},
                {name: 'Period 3', start: '10:30', end: '11:20', break: 0},
                {name: 'Period 4', start: '11:20', end: '12:10', break: 0},
                {name: 'Lunch Break', start: '12:10', end: '13:00', break: 1},
                {name: 'Period 5', start: '13:00', end: '13:50', break: 0},
                {name: 'Period 6', start: '13:50', end: '14:40', break: 0}
            ];
            
            commonPeriods.forEach((period, index) => {
                periodCounter++;
                const container = document.getElementById('periodsContainer');
                
                const periodHtml = `
                    <div class="period-row" id="period_${periodCounter}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <label class="form-label">Period Name *</label>
                                    <input type="text" class="form-control" name="periods[${periodCounter}][period_name]" 
                                           value="${period.name}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" name="periods[${periodCounter}][start_time]" 
                                           value="${period.start}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">End Time *</label>
                                    <input type="time" class="form-control" name="periods[${periodCounter}][end_time]" 
                                           value="${period.end}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Order</label>
                                    <input type="number" class="form-control" name="periods[${periodCounter}][order_index]" 
                                           value="${index}" min="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="periods[${periodCounter}][is_break]">
                                        <option value="0" ${period.break === 0 ? 'selected' : ''}>Class</option>
                                        <option value="1" ${period.break === 1 ? 'selected' : ''}>Break</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePeriod(${periodCounter})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Custom Label</label>
                                    <input type="text" class="form-control" name="periods[${periodCounter}][custom_label]">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Color Code</label>
                                    <input type="color" class="form-control form-control-color" 
                                           name="periods[${periodCounter}][color_code]" value="#${period.break ? 'ffc107' : '007bff'}">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.insertAdjacentHTML('beforeend', periodHtml);
            });
            
            updatePreview();
        }

        function clearAllPeriods() {
            document.getElementById('periodsContainer').innerHTML = '';
            periodCounter = 0;
            updatePreview();
        }

        function updatePreview() {
            // Update selected days preview
            const selectedDays = Array.from(document.querySelectorAll('input[name="days[]"]:checked'))
                                    .map(cb => cb.value);
            const daysPreview = document.getElementById('selectedDaysPreview');
            daysPreview.textContent = selectedDays.length > 0 ? selectedDays.join(', ') : 'None selected';
            
            // Update periods count
            const periodsCount = document.querySelectorAll('#periodsContainer > .period-row').length;
            document.getElementById('periodsCountPreview').textContent = `${periodsCount} periods`;
            
            // Update total schedules
            const totalSchedules = selectedDays.length * periodsCount;
            document.getElementById('totalSchedulesPreview').textContent = `${totalSchedules} schedules (${selectedDays.length} days × ${periodsCount} periods)`;
            
            // Enable/disable submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !(selectedDays.length > 0 && periodsCount > 0);
        }

        // Initialize with one period
        document.addEventListener('DOMContentLoaded', function() {
            addPeriod();
        });

        // Update preview when form changes
        document.addEventListener('change', updatePreview);
        document.addEventListener('input', updatePreview);
    </script>
</body>
</html>