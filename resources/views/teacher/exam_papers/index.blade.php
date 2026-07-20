@extends('layouts.teacher')

@section('title', 'Exam Papers')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">My Exam Papers</h3>
                    <div class="card-tools">
                        <a href="{{ route('teacher.exam-papers.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Paper
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Exam</th>
                                    <th>Class</th>
                                    <th>Subject</th>
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
                                            <a href="{{ route('teacher.exam-papers.show', $paper->id) }}" 
                                               class="btn btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($paper->status == 'draft' || $paper->status == 'rejected')
                                            <a href="{{ route('teacher.exam-papers.edit', $paper->id) }}" 
                                               class="btn btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No exam papers found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    {{ $examPapers->links() }}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection