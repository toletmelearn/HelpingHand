@extends('layouts.app')

@section('title', 'Exam Papers Management')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Exam Papers Management</h4>
                    <a href="{{ route('admin.exam-papers.create') }}" class="btn btn-primary">Add New Paper</a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.exam-papers.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <select name="subject" class="form-select">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="class_section" class="form-select">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)  
                                        <option value="{{ $class }}" {{ request('class_section') == $class ? 'selected' : '' }}>
                                            {{ $class }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="exam_type" class="form-select">
                                    <option value="">All Exam Types</option>
                                    @foreach($examTypes as $type)
                                        <option value="{{ $type }}" {{ request('exam_type') == $type ? 'selected' : '' }}>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="locked" {{ request('status') == 'locked' ? 'selected' : '' }}>Locked</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="teacher_id" class="form-select">
                                    <option value="">All Teachers</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Exam Type</th>
                                    <th>Paper Type</th>
                                    <th>Status</th>
                                    <th>Uploaded By</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($papers as $paper)
                                <tr>
                                    <td>{{ $paper->title }}</td>
                                    <td>{{ $paper->subject }}</td>
                                    <td>{{ $paper->class_section }}</td>
                                    <td>{{ $paper->exam_type }}</td>
                                    <td>
                                        <span class="badge bg-{{ $paper->getPaperTypeBadge() }}">
                                            {{ $paper->paper_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ 
                                            $paper->status == 'draft' ? 'secondary' : 
                                            ($paper->status == 'submitted' ? 'warning' : 
                                            ($paper->status == 'approved' ? 'success' : 
                                            ($paper->status == 'locked' ? 'dark' : 'danger'))) 
                                        }}">
                                            {{ ucfirst($paper->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $paper->uploadedBy->name ?? 'N/A' }}</td>
                                    <td>{{ $paper->created_at->format('d-m-Y') }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.exam-papers.show', $paper) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.exam-papers.edit', $paper) }}" class="btn btn-sm btn-primary">Edit</a>
                                            
                                            @if($paper->status == 'draft')
                                                <a href="{{ route('admin.exam-papers.submit', $paper) }}" class="btn btn-sm btn-warning" 
                                                   onclick="return confirm('Are you sure you want to submit this paper for approval?')">Submit</a>
                                            @elseif($paper->status == 'submitted')
                                                <a href="{{ route('admin.exam-papers.approve', $paper) }}" class="btn btn-sm btn-success" 
                                                   onclick="return confirm('Are you sure you want to approve this paper?')">Approve</a>
                                                <a href="{{ route('admin.exam-papers.edit', $paper) }}" class="btn btn-sm btn-primary">Edit</a>
                                            @elseif($paper->status == 'approved')
                                                <a href="{{ route('admin.exam-papers.lock', $paper) }}" class="btn btn-sm btn-dark" 
                                                   onclick="return confirm('Are you sure you want to lock this paper?')">Lock</a>
                                                <a href="{{ route('admin.exam-papers.download', $paper) }}" class="btn btn-sm btn-info">Download</a>
                                                <a href="{{ route('admin.exam-papers.print', $paper) }}" class="btn btn-sm btn-secondary">Print</a>
                                            @endif
                                            
                                            <a href="{{ route('admin.exam-papers.clone', $paper) }}" class="btn btn-sm btn-outline-secondary">Clone</a>
                                            
                                            <form action="{{ route('admin.exam-papers.destroy', $paper) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this paper?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No exam papers found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        {{ $papers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection