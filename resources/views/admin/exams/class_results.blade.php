@extends('layouts.admin')

@section('title', 'Class Results - Examination')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-graduation-cap"></i> Class Results
                    </h4>
                    <p class="mb-0">View student-wise marks by class and exam</p>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-5">
                                <label for="exam_id" class="form-label">Select Exam</label>
                                <select name="exam_id" id="exam_id" class="form-control">
                                    <option value="">-- Select Exam --</option>
                                    @foreach($exams as $exam)
                                        <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="class_id" class="form-label">Select Class</label>
                                <select name="class_id" id="class_id" class="form-control">
                                    <option value="">-- Select Class --</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">Load Results</button>
                            </div>
                        </div>
                    </form>

                    @if(request('exam_id') && request('class_id'))
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Roll Number</th>
                                        <th>Subject</th>
                                        <th>Marks Obtained</th>
                                        <th>Total Marks</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($results as $result)
                                        <tr>
                                            <td>{{ $result->student_name }}</td>
                                            <td>{{ $result->roll_number }}</td>
                                            <td>{{ $result->subject }}</td>
                                            <td>{{ $result->marks_obtained }}</td>
                                            <td>{{ $result->total_marks }}</td>
                                            <td>{{ $result->percentage }}%</td>
                                            <td>
                                                <span class="badge bg-{{ $result->grade == 'F' ? 'danger' : 'success' }}">
                                                    {{ $result->grade }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">No results found for the selected exam and class</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please select an exam and class to view results.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection