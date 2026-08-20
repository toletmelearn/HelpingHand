<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bell Timing Template - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-journal-text"></i> Edit Template: {{ $template->name }}</h1>
            <a href="{{ route('bell-timing-templates.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Templates</a>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i> Editing this template does NOT change any class it was previously applied to. Use <strong>Apply</strong> again from the Templates list to propagate changes.
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Please fix the following errors:
                <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('bell-timing-templates.update', $template) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Template Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $template->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" value="{{ old('description', $template->description) }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year (hint only)</label>
                            <select class="form-select" name="academic_year">
                                <option value="">None</option>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year }}" {{ old('academic_year', $template->academic_year)==$year?'selected':'' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester (hint only)</label>
                            <select class="form-select" name="semester">
                                <option value="">None</option>
                                <option value="First" {{ old('semester', $template->semester)=='First'?'selected':'' }}>First</option>
                                <option value="Second" {{ old('semester', $template->semester)=='Second'?'selected':'' }}>Second</option>
                                <option value="Third" {{ old('semester', $template->semester)=='Third'?'selected':'' }}>Third</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $slotsForEditor = old('slots') ?: $template->slots->map(fn($s) => [
                    'period_name' => $s->period_name,
                    'start_time' => $s->start_time->format('H:i'),
                    'end_time' => $s->end_time->format('H:i'),
                    'is_break' => $s->is_break ? '1' : '0',
                    'order_index' => $s->order_index,
                    'custom_label' => $s->custom_label,
                    'color_code' => $s->color_code,
                ])->all();
            @endphp
            @include('bell-timing-templates._period-editor', ['slots' => $slotsForEditor])

            <button type="submit" class="btn btn-success mt-3"><i class="bi bi-save"></i> Save Changes</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
