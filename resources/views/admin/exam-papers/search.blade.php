@extends('layouts.admin')

@section('title', 'Search Exam Papers')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Search Exam Papers</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.exam-papers.search') }}">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" name="q" class="form-control" placeholder="Search by title, subject, or teacher..." value="{{ request('q') }}">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-default">
                                    Clear Search
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Exam</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($examPapers as $paper)
                                <tr>
                                    <td>{{ $paper->title }}</td>
                                    <td>{{ $paper->exam->name ?? 'N/A' }}</td>
                                    <td>{{ $paper->class->name ?? 'N/A' }}</td>
                                    <td>{{ $paper->subject }}</td>
                                    <td>{{ $paper->createdBy->name ?? 'Unknown' }}</td>
                                    <td>
                                        @if($paper->status == 'draft')
                                            <span class="badge badge-warning">Draft</span>
                                        @elseif($paper->status == 'submitted')
                                            <span class="badge badge-info">Submitted</span>
                                        @elseif($paper->status == 'approved')
                                            <span class="badge badge-success">Approved</span>
                                        @elseif($paper->status == 'rejected')
                                            <span class="badge badge-danger">Rejected</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($paper->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $paper->created_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.exam-papers.show', $paper->id) }}" 
                                               class="btn btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($paper->status == 'submitted')
                                            <a href="{{ route('admin.exam-papers.approve', $paper->id) }}" 
                                               class="btn btn-success" title="Approve" 
                                               onclick="return confirm('Are you sure you want to approve this exam paper?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="{{ route('admin.exam-papers.reject', $paper->id) }}" 
                                               class="btn btn-danger" title="Reject"
                                               onclick="return confirm('Are you sure you want to reject this exam paper?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            @endif
                                            
                                            @if($paper->file_path)
                                            <a href="{{ route('admin.exam-papers.download', $paper->id) }}" 
                                               class="btn btn-secondary" title="Download File">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No exam papers found matching your search criteria.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    {{ $examPapers->appends(request()->query())->links() }}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection