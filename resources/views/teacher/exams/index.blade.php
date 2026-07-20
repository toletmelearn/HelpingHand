@extends('layouts.teacher')

@section('title', 'My Exams - Teacher Panel')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-file-alt"></i> My Exams</h2>
            <p class="text-muted">Manage exams for your assigned classes</p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Exam
            </a>
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
            <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Exam List</h5>
                </div>
                <div class="card-body">
                    @if($exams->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Exam Name</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Exam Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($exams as $exam)
                                    <tr>
                                        <td>{{ $exam->name }}</td>
                                        <td>{{ $exam->schoolClass->name ?? 'N/A' }}</td>
                                                                                <td>{{ $exam->subjectInfo->name ?? 'N/A' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($exam->exam_date)->format('d M Y') }}</td>
                                        <td>{{ $exam->max_marks }}</td>
                                        <td>
                                            @if($exam->exam_date > now())
                                                <span class="badge bg-warning">Upcoming</span>
                                            @elseif($exam->exam_date <= now() && $exam->exam_date >= now()->subDays(7))
                                                <span class="badge bg-info">Recent</span>
                                            @else
                                                <span class="badge bg-secondary">Past</span>
                                            @endif
                                        </td>
                                        <td>{{ $exam->created_at->format('d M Y') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('teacher.exams.show', $exam->id) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @if($exam->created_by == $teacher->id || $teacher->isExamHead())
                                                <a href="{{ route('teacher.exams.edit', $exam->id) }}" 
                                                   class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('teacher.exams.destroy', $exam->id) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this exam?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center">
                            {{ $exams->links() }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No exams found. 
                            <a href="{{ route('teacher.exams.create') }}">Create your first exam</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection