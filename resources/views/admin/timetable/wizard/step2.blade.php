@extends('layouts.admin')

@section('title', 'Set Up Timetable - Subjects')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">Set Up Timetable</h1>
        <a href="{{ route('timetable.index') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Timetable
        </a>
    </div>

    @include('admin.timetable.wizard._progress', ['currentStep' => 2])

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>{{ $stepLabel }}: {{ $class->name }}</strong> -- list every subject taught in this class, who
        teaches it, and how many periods a week it needs.
        @if($class->teacher_id && !$hasClassTeacherRow)
            The first row below is already set to this class's teacher ({{ $class->teacher->name ?? '' }}) --
            just pick their subject and it will automatically get <strong>Period 1 every day</strong>.
        @endif
    </div>

    <form action="{{ route('timetable.wizard.step2.store', $class) }}" method="POST" id="subjectsForm">
        @csrf
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Subjects for {{ $class->name }}</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="subjectsTable">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th style="width:140px">Periods / Week</th>
                                <th style="width:110px">Double Period</th>
                                <th style="width:110px">Class Teacher's Subject (Period 1)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="subjectsRows">
                            @php $rowIndex = 0; @endphp
                            @if($class->teacher_id && !$hasClassTeacherRow)
                                <tr>
                                    <td>
                                        <select name="rows[{{ $rowIndex }}][subject_id]" class="form-select form-select-sm">
                                            <option value="">-- Choose --</option>
                                            @foreach($subjects as $subject)
                                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rows[{{ $rowIndex }}][teacher_id]" class="form-select form-select-sm">
                                            @foreach($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" {{ $teacher->id == $class->teacher_id ? 'selected' : '' }}>{{ $teacher->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" min="1" max="12" name="rows[{{ $rowIndex }}][periods_per_week]" class="form-control form-control-sm"></td>
                                    <td class="text-center"><input type="checkbox" name="rows[{{ $rowIndex }}][require_consecutive]" value="1" class="form-check-input"></td>
                                    <td class="text-center"><input type="checkbox" name="rows[{{ $rowIndex }}][is_class_teacher]" value="1" checked class="form-check-input"></td>
                                    <td></td>
                                </tr>
                                @php $rowIndex++; @endphp
                            @endif
                            @foreach($assignments as $assignment)
                                <tr>
                                    <td>
                                        <select name="rows[{{ $rowIndex }}][subject_id]" class="form-select form-select-sm">
                                            <option value="">-- Choose --</option>
                                            @foreach($subjects as $subject)
                                                <option value="{{ $subject->id }}" {{ $assignment->subject_id == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rows[{{ $rowIndex }}][teacher_id]" class="form-select form-select-sm">
                                            @foreach($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" {{ $assignment->teacher_id == $teacher->id ? 'selected' : '' }}>{{ $teacher->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" min="1" max="12" name="rows[{{ $rowIndex }}][periods_per_week]" value="{{ $assignment->periods_per_week }}" class="form-control form-control-sm"></td>
                                    <td class="text-center"><input type="checkbox" name="rows[{{ $rowIndex }}][require_consecutive]" value="1" {{ $assignment->require_consecutive ? 'checked' : '' }} class="form-check-input"></td>
                                    <td class="text-center"><input type="checkbox" name="rows[{{ $rowIndex }}][is_class_teacher]" value="1" {{ $assignment->is_class_teacher ? 'checked' : '' }} class="form-check-input"></td>
                                    <td></td>
                                </tr>
                                @php $rowIndex++; @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" id="addSubjectRow" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ $previous ? route('timetable.wizard.step2', $previous) : route('timetable.wizard.step1') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> {{ $previous ? 'Previous Class' : 'Back to Class Teachers' }}
                </a>
                <button type="submit" class="btn btn-primary">
                    Save and {{ $next ? 'Next Class' : 'Continue' }} <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<template id="subjectRowTemplate">
    <tr>
        <td>
            <select name="rows[__INDEX__][subject_id]" class="form-select form-select-sm">
                <option value="">-- Choose --</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="rows[__INDEX__][teacher_id]" class="form-select form-select-sm">
                <option value="">-- Choose --</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" min="1" max="12" name="rows[__INDEX__][periods_per_week]" class="form-control form-control-sm"></td>
        <td class="text-center"><input type="checkbox" name="rows[__INDEX__][require_consecutive]" value="1" class="form-check-input"></td>
        <td class="text-center"><input type="checkbox" name="rows[__INDEX__][is_class_teacher]" value="1" class="form-check-input"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger removeSubjectRow"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

<script>
    (function () {
        let nextIndex = {{ $rowIndex }};
        const tbody = document.getElementById('subjectsRows');
        const template = document.getElementById('subjectRowTemplate');

        document.getElementById('addSubjectRow').addEventListener('click', function () {
            const html = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.removeSubjectRow')) {
                e.target.closest('tr').remove();
            }
        });
    })();
</script>
@endsection
