@extends('layouts.admin')

@section('title', 'Exams Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Exams Management</h4>
                    <a href="{{ route('admin.exams.create') }}" class="btn btn-primary">Add New Exam</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Total Marks</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exams as $exam)
                                <tr>
                                    <td>{{ $exam->id }}</td>
                                    <td>{{ $exam->name }}</td>
                                    <td>{{ $exam->exam_type }}</td>
                                    <td>{{ $exam->class_name }}</td>
                                    <td>{{ $exam->subject }}</td>
                                    <td>{{ $exam->exam_date->format('d M Y') }}</td>
                                    <td>{{ $exam->total_marks }}</td>
                                    <td>
                                        <span class="badge bg-{{ $exam->status == 'active' ? 'success' : ($exam->status == 'scheduled' ? 'info' : ($exam->status == 'cancelled' ? 'danger' : 'secondary')) }}">
                                            {{ ucfirst($exam->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $exam->createdBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('admin.exams.show', $exam) }}" class="btn btn-sm btn-info">View</a>
                                        <a href="{{ route('admin.exams.edit', $exam) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.exams.destroy', $exam) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this exam?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No exams found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $exams->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
