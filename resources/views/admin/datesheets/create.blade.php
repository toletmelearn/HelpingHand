@extends('layouts.admin')

@section('title', 'New Datesheet - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0"><i class="fas fa-calendar-alt"></i> New Datesheet</h2>
            <p class="text-muted mb-0">Step 1: define the examination event and which classes/sections it covers. Add subject-wise entries next.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.datesheets.store') }}" method="POST">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Term 1 Examinations 2026-27" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Exam Type / Term *</label>
                        <input type="text" name="exam_type" class="form-control" placeholder="e.g. Term 1" required value="{{ old('exam_type') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Academic Session *</label>
                        <select name="academic_session_id" class="form-select" required>
                            <option value="">-- Choose --</option>
                            @foreach($sessions as $session)
                                <option value="{{ $session->id }}" {{ $session->is_current ? 'selected' : '' }}>{{ $session->name }}{{ $session->is_current ? ' (current)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required value="{{ old('start_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">End Date *</label>
                        <input type="date" name="end_date" class="form-control" required value="{{ old('end_date') }}">
                    </div>
                </div>

                <hr>
                <h5>Applicable Classes / Sections *</h5>
                <p class="text-muted small">Check each class this datesheet covers. Leave "Section" as "Whole class" unless the exam is section-specific.</p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead><tr><th></th><th>Class</th><th>Section</th></tr></thead>
                        <tbody>
                            @foreach($classes as $class)
                                <tr>
                                    <td><input type="checkbox" name="class_ids[]" value="{{ $class->id }}" class="form-check-input"></td>
                                    <td>{{ $class->name }}</td>
                                    <td>
                                        <select name="section_ids[{{ $class->id }}]" class="form-select form-select-sm">
                                            <option value="">Whole class</option>
                                            @foreach($class->validSectionIds() as $sectionId)
                                                @php $section = \App\Models\Section::find($sectionId); @endphp
                                                @if($section)
                                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Draft</button>
                <a href="{{ route('admin.datesheets.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
