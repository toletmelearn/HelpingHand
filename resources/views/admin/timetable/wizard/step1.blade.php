@extends('layouts.admin')

@section('title', 'Set Up Timetable - Class Teachers')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">Set Up Timetable</h1>
        <a href="{{ route('timetable.index') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Timetable
        </a>
    </div>

    @include('admin.timetable.wizard._progress', ['currentStep' => 1])

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Every class in your school is listed below. Pick who is the <strong>class teacher</strong> for each one --
        the teacher who takes attendance and looks after that class. You don't have to fill in every class right
        now; you can leave some blank and come back later. This does not decide their subject yet -- that's the
        next step.
    </div>

    <form action="{{ route('timetable.wizard.step1.store') }}" method="POST">
        @csrf
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Class Teacher for each Class</h6>
            </div>
            <div class="card-body">
                @if($classes->isEmpty())
                    <p class="text-muted mb-0">No active classes found. Add classes first.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Class Teacher</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($classes as $class)
                                    <tr>
                                        <td>{{ $class->name }}</td>
                                        <td>
                                            <select name="class_teachers[{{ $class->id }}]" class="form-select form-select-sm">
                                                <option value="">-- None yet --</option>
                                                @foreach($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" {{ old("class_teachers.{$class->id}", $class->teacher_id) == $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" {{ $classes->isEmpty() ? 'disabled' : '' }}>
                    Save and Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
