@extends('layouts.admin')

@section('title', 'Assign Sections to ' . $class->name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Assign Sections to {{ $class->name }}</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('admin.classes.save-section-assignment', $class) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-3">
                            <label for="sections">Select Sections:</label>
                            <select name="sections[]" id="sections" class="form-control select2" multiple>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}" 
                                        {{ in_array($section->id, $class->sections->pluck('id')->toArray()) ? 'selected' : '' }}>
                                        {{ $section->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple sections</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Assign Sections</button>
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
        placeholder: "Select sections...",
        allowClear: true
    });
});
</script>
@endsection
