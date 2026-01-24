@extends('layouts.app')

@section('title', 'Assign Subjects to ' . $class->name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Assign Subjects to {{ $class->name }}</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('admin.classes.save-subject-assignment', $class) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-3">
                            <label for="subjects">Select Subjects:</label>
                            <select name="subjects[]" id="subjects" class="form-control select2" multiple>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" 
                                        {{ in_array($subject->id, $assignedSubjects) ? 'selected' : '' }}>
                                        {{ $subject->name }} ({{ $subject->code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple subjects</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Assign Subjects</button>
                            <a href="{{ route('admin.classes.show', $class) }}" class="btn btn-secondary">Back to Class</a>
                        </div>
                    </form>
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
        placeholder: "Select subjects...",
        allowClear: true
    });
});
</script>
@endsection