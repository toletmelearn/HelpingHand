@extends('layouts.admin')

@section('title', 'Assign Teachers to Subjects')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Assign Teachers to Subjects</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @foreach($subjects as $subject)
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5>{{ $subject->name }} ({{ $subject->code }})</h5>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('admin.classes.save-subject-teacher-assignment') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">

                                    <div class="form-group mb-3">
                                        <label for="teacher_ids_{{ $subject->id }}">Select Teachers for this Subject:</label>
                                        <select name="teacher_ids[]" id="teacher_ids_{{ $subject->id }}" class="form-control select2" multiple>
                                            @foreach($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" 
                                                    {{ $subject->teachers->contains($teacher->id) ? 'selected' : '' }}>
                                                    {{ $teacher->name }} ({{ $teacher->designation }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="class_id_{{ $subject->id }}">Assign to Class (Optional):</label>
                                        <select name="class_id" id="class_id_{{ $subject->id }}" class="form-control">
                                            <option value="">-- No specific class --</option>
                                            @foreach(App\Models\ClassManagement::all() as $class)
                                                <option value="{{ $class->id }}">{{ $class->name }} {{ $class->section ? '- '.$class->section : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label>Primary Teacher:</label>
                                        @foreach($teachers as $teacher)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="primary_teacher_id" id="primary_{{ $subject->id }}_{{ $teacher->id }}" 
                                                    value="{{ $teacher->id }}" 
                                                    {{ (optional($subject->teachers->first())->id ?? '') == $teacher->id ? 'checked' : '' }}>
                                                <label class="form-check-label" for="primary_{{ $subject->id }}_{{ $teacher->id }}">
                                                    {{ $teacher->name }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Assign Teachers</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select teachers...",
        allowClear: true
    });
});
</script>
@endsection
