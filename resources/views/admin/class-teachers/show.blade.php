@extends('layouts.admin')

@section('title', 'Class Teacher Assignment - ' . $schoolClass->name . ' - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Class Teacher: {{ $schoolClass->name }}</h2>
                    <p class="text-muted mb-0">Academic year {{ $academicYear }}.</p>
                </div>
                <div class="page-title-right">
                    <a href="{{ route('admin.class-teachers.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Classes
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($rows->count() <= 1 && $schoolClass->validSectionIds() === [])
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            This class has no sections configured yet -- only the "whole class" slot below is available. Sections must be attached to a class before a section-specific class teacher can be assigned.
        </div>
    @endif

    @foreach($rows as $row)
        <div class="card mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users"></i> {{ $row['name'] }}</h5>
                @if($row['current'])
                    <span class="badge bg-success"><i class="fas fa-star"></i> Class Teacher: {{ $row['current']->teacher->name ?? 'N/A' }}</span>
                @else
                    <span class="badge bg-secondary">No class teacher assigned</span>
                @endif
            </div>
            <div class="card-body">
                @if($row['current'])
                    <p class="mb-3">
                        <strong>{{ $row['current']->teacher->name ?? 'N/A' }}</strong>
                        (teaches {{ $row['current']->subject->name ?? 'N/A' }} to this class)
                    </p>
                    <form action="{{ route('admin.class-teachers.remove', $row['current']) }}" method="POST" class="d-inline mb-3"
                          onsubmit="return confirm('Remove {{ $row['current']->teacher->name ?? 'this teacher' }} as class teacher of {{ $schoolClass->name }} ({{ $row['name'] }})?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-user-slash"></i> Remove as Class Teacher
                        </button>
                    </form>
                    <hr>
                    <p class="text-muted small mb-2">Change the class teacher:</p>
                @endif

                <form action="{{ route('admin.class-teachers.assign', $schoolClass) }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="section_id" value="{{ $row['id'] }}">
                    <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                    <div class="col-md-4">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- Choose Teacher --</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Subject they teach this class/section</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Choose Subject --</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-check"></i> {{ $row['current'] ? 'Change Class Teacher' : 'Assign Class Teacher' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
