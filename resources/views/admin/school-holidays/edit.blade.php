@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12 col-lg-8">
            <h1 class="h3 mb-4">Edit Holiday</h1>

            <div class="card shadow mb-4">
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.school-holidays.update', $holiday->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="text" class="form-control" value="{{ $holiday->academic_year }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Holiday Name</label>
                            <input type="text" name="holiday_name" class="form-control" value="{{ old('holiday_name', $holiday->holiday_name) }}" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $holiday->start_date->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $holiday->end_date->format('Y-m-d')) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="holiday_type" class="form-select" required>
                                <option value="leave" @selected(old('holiday_type', $holiday->holiday_type) === 'leave')>Leave</option>
                                <option value="festival" @selected(old('holiday_type', $holiday->holiday_type) === 'festival')>Festival</option>
                                <option value="special" @selected(old('holiday_type', $holiday->holiday_type) === 'special')>Special</option>
                                <option value="exam_break" @selected(old('holiday_type', $holiday->holiday_type) === 'exam_break')>Exam Break</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (optional)</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $holiday->description) }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Holiday</button>
                        <a href="{{ route('admin.school-holidays.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
