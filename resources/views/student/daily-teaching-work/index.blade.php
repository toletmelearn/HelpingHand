@extends('layouts.app')

@section('title', 'Daily Teaching Work - My Class')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Daily Teaching Work - Class {{ Auth::user()->student->class }} ({{ Auth::user()->student->section }})</h4>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select name="subject" class="form-control">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="date" class="form-control" value="{{ request('date') }}" placeholder="Select Date">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('student.daily-teaching-work.index') }}" class="btn btn-secondary w-100 mt-1">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Daily Work List -->
                    <div class="row">
                        @forelse($dailyWorks as $work)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <span class="badge bg-primary">{{ $work->date->format('d-m-Y') }}</span>
                                        <br>
                                        <small class="text-muted">{{ $work->subject }}</small>
                                    </h6>
                                    <p class="card-text small">
                                        <strong>Topic:</strong> {{ Str::limit($work->topic_covered, 50) }}
                                        <br>
                                        <strong>Teacher:</strong> {{ $work->teacher->name ?? 'N/A' }}
                                    </p>
                                    
                                    @if($work->hasAttachments())
                                    <div class="mb-2">
                                        <span class="badge bg-info">{{ $work->getAttachmentCount() }} Attachment(s)</span>
                                    </div>
                                    @endif
                                    
                                    @if($work->hasHomework())
                                    <div class="mb-2">
                                        <span class="badge bg-warning">Homework Assigned</span>
                                    </div>
                                    @endif
                                </div>
                                <div class="card-footer">
                                    <a href="{{ route('student.daily-teaching-work.show', $work) }}" class="btn btn-sm btn-outline-primary w-100">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-book-open" style="font-size: 3rem; opacity: 0.3;"></i>
                                <h5 class="mt-3">No Daily Teaching Work Found</h5>
                                <p class="text-muted">No teaching work has been posted for your class yet.</p>
                            </div>
                        </div>
                        @endforelse
                    </div>
                    
                    {{ $dailyWorks->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection