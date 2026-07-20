@extends('layouts.parent')

@section('title', 'Exam Papers')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Published Exam Papers for {{ $student->name }}</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    @if($examPapers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Published Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($examPapers as $paper)
                                <tr>
                                    <td>{{ $paper->title }}</td>
                                    <td>{{ $paper->exam->name ?? 'N/A' }}</td>
                                    <td>{{ $paper->subject }}</td>
                                    <td>{{ $paper->createdBy->name ?? 'Unknown' }}</td>
                                    <td>{{ $paper->created_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            @if($paper->file_path)
                                            <a href="{{ route('parent.exam-papers.download', $paper->id) }}" 
                                               class="btn btn-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                            <a href="{{ route('parent.exam-papers.show', $paper->id) }}" 
                                               class="btn btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    {{ $examPapers->links() }}
                    @else
                    <div class="text-center py-5">
                        <i class="fas fa-file fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Published Exam Papers</h4>
                        <p class="text-muted">There are currently no published exam papers for your child's class.</p>
                    </div>
                    @endif
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection