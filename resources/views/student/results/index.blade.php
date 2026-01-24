@extends('layouts.app')

@section('title', 'My Results')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>My Results</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($results->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Term</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $result)
                                    <tr>
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
                                            <a href="{{ route('student.results.show', $result) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('student.results.generate-pdf', $result) }}" class="btn btn-sm btn-primary" target="_blank">PDF</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        {{ $results->links() }}
                    @else
                        <div class="alert alert-info">
                            No results available yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection