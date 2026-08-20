<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save Bell Timing as Template - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5" style="max-width: 720px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-copy"></i> Save Bell Timing as Template</h1>
            <a href="{{ route('bell-timing-templates.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Templates</a>
        </div>

        <p class="text-muted">Pick a class and one day -- that day's schedule becomes a new, reusable template. The class's own Bell Timing is not changed.</p>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('bell-timing.save-as-template.store') }}" method="POST" class="card">
            @csrf
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Class/Section *</label>
                    <select class="form-select" name="class_section" required>
                        <option value="">Select Class</option>
                        @foreach($classSections as $section)
                            <option value="{{ $section }}" {{ old('class_section', $presetClassSection)==$section?'selected':'' }}>{{ $section }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Day to copy from *</label>
                    <select class="form-select" name="day" required>
                        <option value="">Select Day</option>
                        @foreach($days as $day)
                            <option value="{{ $day }}" {{ old('day')==$day?'selected':'' }}>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Template Name *</label>
                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="e.g., Primary School - 8 Periods" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="description" value="{{ old('description') }}">
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Save as Template</button>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
