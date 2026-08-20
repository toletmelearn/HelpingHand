<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Bell Timing Template - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-send"></i> Apply Bell Timing</h1>
            <a href="{{ route('bell-timing-templates.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Templates</a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Template: {{ $template->name }}</h5></div>
            <div class="card-body">
                <p class="text-muted mb-2">{{ $template->description }}</p>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Period</th><th>Start</th><th>End</th><th>Type</th></tr></thead>
                    <tbody>
                        @foreach($template->slots as $slot)
                            <tr>
                                <td>{{ $slot->period_name }}</td>
                                <td>{{ $slot->start_time->format('h:i A') }}</td>
                                <td>{{ $slot->end_time->format('h:i A') }}</td>
                                <td>{{ $slot->is_break ? 'Break' : 'Class' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form action="{{ route('bell-timing-templates.apply.preview', $template) }}" method="POST">
            @csrf
            <div class="card mb-4">
                <div class="card-header bg-info text-white"><h5 class="mb-0">1. Select Days</h5></div>
                <div class="card-body">
                    <div class="row">
                        @foreach($days as $day)
                            <div class="col-md-3 mb-2 form-check">
                                <input type="checkbox" class="form-check-input" name="days[]" value="{{ $day }}" id="day_{{ $day }}" {{ in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="day_{{ $day }}">{{ $day }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">2. Apply To Classes</h5>
                    <div>
                        <button type="button" class="btn btn-light btn-sm" onclick="toggleAll(true)">Select All</button>
                        <button type="button" class="btn btn-light btn-sm" onclick="toggleAll(false)">Clear All</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($classSections as $section)
                            <div class="col-md-3 mb-2 form-check">
                                <input type="checkbox" class="form-check-input class-check" name="classes[]" value="{{ $section }}" id="class_{{ $loop->index }}">
                                <label class="form-check-label" for="class_{{ $loop->index }}">{{ $section }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-secondary text-white"><h5 class="mb-0">Academic Year / Semester for applied schedules</h5></div>
                <div class="card-body row">
                    <div class="col-md-6 mb-2">
                        <select class="form-select" name="academic_year">
                            <option value="">None</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}" {{ $template->academic_year==$year?'selected':'' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <select class="form-select" name="semester">
                            <option value="">None</option>
                            <option value="First" {{ $template->semester=='First'?'selected':'' }}>First</option>
                            <option value="Second" {{ $template->semester=='Second'?'selected':'' }}>Second</option>
                            <option value="Third" {{ $template->semester=='Third'?'selected':'' }}>Third</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-eye"></i> Review Before Applying</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(state) { document.querySelectorAll('.class-check').forEach(cb => cb.checked = state); }
    </script>
</body>
</html>
