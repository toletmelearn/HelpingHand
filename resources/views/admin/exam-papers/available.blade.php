@extends('layouts.app')

@section('title', 'Available Exam Papers')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Available Exam Papers</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Exam Type</th>
                                    <th>Paper Type</th>
                                    <th>Exam Date</th>
                                    <th>Uploaded By</th>
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
                                    <td>{{ $paper->exam_date ? $paper->exam_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>{{ $paper->uploadedBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.exam-papers.show', $paper) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.exam-papers.download', $paper) }}" class="btn btn-sm btn-success">Download</a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No available exam papers found.</td>
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