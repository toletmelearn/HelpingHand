@extends('layouts.admin')

@section('title', 'Set Up Timetable - Check & Create')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">Set Up Timetable</h1>
        <a href="{{ route('timetable.index') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Back to Timetable
        </a>
    </div>

    @include('admin.timetable.wizard._progress', ['currentStep' => 4])

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        A quick check before creating the timetable. Nothing here stops you from continuing -- these are just
        things worth fixing if you want a cleaner result. You can always come back and Generate again later.
    </div>

    @php
        $flaggedClasses = collect($report['grid_capacity'])->filter(fn ($r) => $r['over_required']);
        $flaggedTeachers = collect($report['teacher_load'])->filter(fn ($r) => $r['over_available'] || $r['over_threshold']);
        $readiness = collect($report['class_teacher_readiness']);
        $conflicts = collect($report['conflicts']);
        $allClear = $flaggedClasses->isEmpty() && $flaggedTeachers->isEmpty() && $readiness->isEmpty() && $conflicts->isEmpty();
    @endphp

    @if($allClear)
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Everything looks ready. No issues found.</div>
    @endif

    @if($readiness->isNotEmpty())
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Classes without a Class Teacher</h6></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($readiness as $row)
                        <li>{{ $row['sentence'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($flaggedClasses->isNotEmpty())
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Classes Needing More Periods Than the Week Has</h6></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($flaggedClasses as $row)
                        <li class="text-danger">{{ $row['sentence'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($flaggedTeachers->isNotEmpty())
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Teachers Overloaded</h6></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($flaggedTeachers as $row)
                        <li class="text-danger">{{ $row['sentence'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($conflicts->isNotEmpty())
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Conflicts Found</h6></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($conflicts as $conflict)
                        <li class="text-danger">{{ $conflict['sentence'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <a href="{{ route('timetable.wizard.step3') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Style
            </a>
            <a href="{{ route('timetable.generate.form', ['style' => $style, 'select_all' => 1]) }}" class="btn btn-warning btn-lg">
                <i class="fas fa-magic"></i> Continue to Create Timetable
            </a>
        </div>
    </div>
</div>
@endsection
