@extends('layouts.admin')

@section('title', $datesheet->name . ' - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0"><i class="fas fa-calendar-alt"></i> {{ $datesheet->name }}</h2>
                <p class="text-muted mb-0">{{ $datesheet->exam_type }} &middot; {{ $datesheet->academicSession->name ?? 'N/A' }} &middot; {{ $datesheet->start_date->format('d M Y') }} - {{ $datesheet->end_date->format('d M Y') }}</p>
            </div>
            <div>
                <a href="{{ route('admin.datesheets.pdf', $datesheet) }}" class="btn btn-outline-primary"><i class="fas fa-file-pdf"></i> Download PDF</a>
                <a href="{{ route('admin.datesheets.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <strong>Status:</strong>
                @php $badge = ['draft'=>'secondary','under_review'=>'warning','approved'=>'info','published'=>'success'][$datesheet->status] ?? 'secondary'; @endphp
                <span class="badge bg-{{ $badge }} fs-6">{{ str_replace('_',' ',ucfirst($datesheet->status)) }}</span>
                @if($datesheet->supersededBy)
                    <span class="badge bg-dark">Superseded by a later revision</span>
                @endif
            </div>
            <div>
                @if($datesheet->status === 'draft')
                    <form action="{{ route('admin.datesheets.submit', $datesheet) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane"></i> Submit for Review</button>
                    </form>
                @elseif($datesheet->status === 'under_review')
                    <form action="{{ route('admin.datesheets.approve', $datesheet) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form action="{{ route('admin.datesheets.reject', $datesheet) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Send Back to Draft</button>
                    </form>
                @elseif($datesheet->status === 'approved')
                    <form action="{{ route('admin.datesheets.publish', $datesheet) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Publish this datesheet? This creates the real Exam records and cannot be silently undone -- a correction would require a new revision.');">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="fas fa-bullhorn"></i> Publish</button>
                    </form>
                    <form action="{{ route('admin.datesheets.reject', $datesheet) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Send Back to Draft</button>
                    </form>
                @elseif($datesheet->status === 'published' && !$datesheet->supersededBy)
                    <form action="{{ route('admin.datesheets.revise', $datesheet) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-copy"></i> Create Revision</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light"><h5 class="mb-0">Applicable Classes/Sections</h5></div>
        <div class="card-body">
            @foreach($datesheet->classes as $c)
                <span class="badge bg-primary me-1">{{ $c->schoolClass->name ?? '?' }}{{ $c->section ? ' - '.$c->section->name : ' (whole class)' }}</span>
            @endforeach
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-list"></i> Entries</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr><th>Class</th><th>Section</th><th>Subject</th><th>Date</th><th>Day</th><th>Time</th><th>Marks</th><th>Room</th><th>Exam Linked</th>@if($datesheet->isEditable())<th></th>@endif</tr>
                    </thead>
                    <tbody>
                        @forelse($datesheet->entries as $entry)
                            <tr>
                                <td>{{ $entry->schoolClass->name ?? '?' }}</td>
                                <td>{{ $entry->section->name ?? 'Whole class' }}</td>
                                <td>{{ $entry->subject->name ?? '?' }}</td>
                                <td>{{ $entry->exam_date->format('d M Y') }}</td>
                                <td>{{ $entry->day_of_week }}</td>
                                <td>{{ $entry->start_time }} - {{ $entry->end_time }}</td>
                                <td>{{ $entry->total_marks }} (pass {{ $entry->passing_marks }})</td>
                                <td>{{ $entry->room ?? '-' }}</td>
                                <td>{{ $entry->exam_id ? '#'.$entry->exam_id : '-' }}</td>
                                @if($datesheet->isEditable())
                                <td>
                                    <form action="{{ route('admin.datesheets.entries.destroy', [$datesheet, $entry]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this entry?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-4 text-muted">No entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($datesheet->isEditable())
    <div class="card">
        <div class="card-header bg-light"><h5 class="mb-0">Add Entry</h5></div>
        <div class="card-body">
            <form action="{{ route('admin.datesheets.entries.store', $datesheet) }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select name="school_class_id" class="form-select" required>
                        <option value="">-- Choose --</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">Whole class</option>
                        @foreach($datesheet->classes as $c)
                            @if($c->section)
                                <option value="{{ $c->section->id }}">{{ $c->section->name }} ({{ $c->schoolClass->name ?? '' }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Choose --</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="exam_date" class="form-control" required min="{{ $datesheet->start_date->toDateString() }}" max="{{ $datesheet->end_date->toDateString() }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Start</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">End</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Total</label>
                    <input type="number" name="total_marks" class="form-control" value="100">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Room</label>
                    <input type="text" name="room" class="form-control">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Instructions</label>
                    <input type="text" name="instructions" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add Entry</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
