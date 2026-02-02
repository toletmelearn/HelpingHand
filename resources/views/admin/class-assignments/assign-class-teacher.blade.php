@extends('layouts.admin')

@section('title', 'Assign Class Teacher to ' . $class->name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Assign Class Teacher to {{ $class->name }}</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('admin.classes.save-class-teacher-assignment', $class) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-3">
                            <label for="teacher_id">Select Class Teacher:</label>
                            <select name="teacher_id" id="teacher_id" class="form-control select2">
                                <option value="">-- Select a Teacher --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" 
                                        {{ $currentTeacher && $currentTeacher->id == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }} ({{ $teacher->designation }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Assign Class Teacher</button>
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
        placeholder: "Select a teacher...",
        allowClear: true
    });
});
</script>
@endsection
