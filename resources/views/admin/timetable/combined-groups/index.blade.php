@extends('layouts.admin')

@section('title', 'Combined Class Groups - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-object-group"></i> Combined Class Groups</h2>
                    <p class="text-muted mb-0">Subjects taught to several classes together in one period.</p>
                </div>
                <a href="{{ route('combined-class-groups.create') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle"></i> New Group
                </a>
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
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @forelse($groups as $group)
        <div class="card shadow mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $group->name }}</strong>
                    <span class="text-muted"> &mdash; {{ $group->subject->name ?? 'N/A' }} &mdash; {{ $group->academicSession->name ?? 'N/A' }}</span>
                </div>
                <form action="{{ route('combined-class-groups.destroy', $group) }}" method="POST" onsubmit="return confirm('Remove this combined group? Any timetable slots already placed for it stay, but detach from the group.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Remove</button>
                </form>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Members:</strong>
                    @foreach($group->members as $member)
                        <span class="badge bg-secondary">{{ $member->schoolClass->name ?? '?' }}{{ $member->section ? ' '.$member->section->name : '' }}</span>
                    @endforeach
                </p>

                <form action="{{ route('timetable.combined.store') }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="combined_class_group_id" value="{{ $group->id }}">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Teacher</label>
                        <select name="teacher_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose --</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Period</label>
                        <select name="bell_timing_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose --</option>
                            @foreach($bellTimings as $timing)
                                <option value="{{ $timing->id }}">{{ $timing->day_of_week }} - {{ $timing->period_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Room</label>
                        <input type="text" name="room_number" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100">
                            <i class="fas fa-calendar-plus"></i> Place
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="text-center py-5">
            <i class="fas fa-object-group fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No combined class groups yet.</h5>
        </div>
    @endforelse
</div>
@endsection
