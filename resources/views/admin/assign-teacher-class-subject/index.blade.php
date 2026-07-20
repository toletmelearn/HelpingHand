@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h2>Assign Class Subject to Teacher</h2>
            
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            
            <form method="POST" action="{{ route('admin.assign-teacher-class-subject.store') }}">
                @csrf
                
                <div class="form-group">
                    <label for="teacher_id">Teacher</label>
                    <select name="teacher_id" id="teacher_id" class="form-control" required>
                        <option value="">Select Teacher</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }} ({{ $teacher->email }})</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select name="subject_id" id="subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Assign</button>
            </form>
        </div>
    </div>
</div>
@endsection