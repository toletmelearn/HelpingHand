@extends('layouts.admin')

@section('title', 'Add Calendar Entry')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Add Calendar Entry</h1>
        <a href="{{ route('admin.academic-events.index') }}" class="btn btn-secondary">Back to Calendar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.academic-events.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror"
                                   id="title" name="title" value="{{ old('title') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="type" class="form-label">Type *</label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                @foreach(['holiday' => 'Holiday', 'exam' => 'Exam', 'event' => 'Event', 'ptm' => 'Parent-Teacher Meeting', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                                   id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date *</label>
                            <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                                   id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="academic_session_id" class="form-label">Academic Session</label>
                    <select class="form-select @error('academic_session_id') is-invalid @enderror" id="academic_session_id" name="academic_session_id">
                        <option value="">— None —</option>
                        @foreach($academicSessions as $session)
                            <option value="{{ $session->id }}" @selected((string) old('academic_session_id') === (string) $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                    @error('academic_session_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror"
                              id="description" name="description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Entry</button>
                    <a href="{{ route('admin.academic-events.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
