@extends('layouts.app')

@section('title', 'My Results & Exams')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>My Assigned Exams & Results</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Exams for Marks Entry -->
                    <div class="mb-5">
                        <h5>Exams - Enter Marks</h5>
                        @if($exams->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Exam Name</th>
                                            <th>Class</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Total Marks</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($exams as $exam)
                                        <tr>
                                            <td>{{ $exam->name }}</td>
                                            <td>{{ $exam->class_name }}</td>
                                            <td>{{ $exam->subject }}</td>
                                            <td>{{ $exam->exam_date->format('d M Y') }}</td>
                                            <td>{{ $exam->total_marks }}</td>
                                            <td>
                                                <span class="badge bg-{{ $exam->status == 'active' ? 'success' : ($exam->status == 'scheduled' ? 'info' : ($exam->status == 'completed' ? 'secondary' : 'danger')) }}">
                                                    {{ ucfirst($exam->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('teacher.results.enter-marks', $exam) }}" class="btn btn-sm btn-primary">
                                                    Enter Marks
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info">
                                No exams assigned to you for marks entry.
                            </div>
                        @endif
                    </div>

                    <!-- My Results Overview -->
                    <div>
                        <h5>Results Overview</h5>
                        @if($results->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Exam</th>
                                            <th>Subject</th>
                                            <th>Marks</th>
                                            <th>Total</th>
                                            <th>Percentage</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($results as $result)
                                        <tr>
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
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            {{ $results->links() }}
                        @else
                            <div class="alert alert-info">
                                No results to display.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection