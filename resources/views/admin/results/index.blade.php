@extends('layouts.admin')

@section('title', 'Results Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Results Management</h4>
                    <a href="{{ route('admin.results.create') }}" class="btn btn-primary">Add New Result</a>
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
                                    <th>Student</th>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Marks</th>
                                    <th>Total</th>
                                    <th>%</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Term</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $result)
                                <tr>
                                    <td>{{ $result->id }}</td>
                                    <td>{{ $result->student->name ?? 'N/A' }}</td>
                                    <td>{{ $result->exam->name ?? 'N/A' }}</td>
                                    <td>{{ $result->subject }}</td>
                                    <td>{{ $result->marks_obtained }}</td>
                                    <td>{{ $result->total_marks }}</td>
                                    <td>{{ $result->percentage }}%</td>
                                    <td>{{ $result->grade }}</td>
                                    <td>
                                        <span class="badge bg-{{ $result->result_status == 'pass' ? 'success' : 'danger' }}">
                                            {{ ucfirst($result->result_status) }}
                                        </span>
                                    </td>
                                    <td>{{ $result->term }}</td>
                                    <td>
                                        <a href="{{ route('admin.results.show', $result) }}" class="btn btn-sm btn-info">View</a>
                                        <a href="{{ route('admin.results.edit', $result) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.results.destroy', $result) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this result?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center">No results found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $results->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
