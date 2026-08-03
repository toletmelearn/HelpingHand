@extends('layouts.admin')

@section('title', 'Set Up Timetable - Style')

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

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Choose how the week should look. Whichever you pick, the <strong>class teacher's subject still takes
        Period 1 every day</strong> automatically -- that never changes.
    </div>

    <form action="{{ route('timetable.wizard.step3') }}" method="GET">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Timetable Style</h6>
            </div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="radio" class="form-check-input" name="style" id="styleRotating" value="rotating" checked>
                    <label class="form-check-label" for="styleRotating">
                        <strong>Different Each Day</strong> -- each subject's periods are spread across different
                        days of the week (the usual way).
                    </label>
                </div>
                <div class="form-check">
                    <input type="radio" class="form-check-input" name="style" id="styleFixedDaily" value="fixed_daily">
                    <label class="form-check-label" for="styleFixedDaily">
                        <strong>Same Every Day</strong> -- one day's pattern repeats every day (e.g. Maths is
                        always Period 3, every single day).
                    </label>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ $firstUrl ?? route('timetable.wizard.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Subjects
                </a>
                <button type="submit" class="btn btn-primary">
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
