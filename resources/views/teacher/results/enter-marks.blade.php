@extends('layouts.app')

@section('title', 'Enter Marks - ' . $exam->name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Enter Marks for {{ $exam->name }} - {{ $exam->subject }}</h4>
                    <p><strong>Class:</strong> {{ $exam->class_name }} | <strong>Date:</strong> {{ $exam->exam_date->format('d M Y') }} | <strong>Total Marks:</strong> {{ $exam->total_marks }}</p>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('teacher.results.save-marks', $exam) }}" method="POST">
                        @csrf
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Roll Number</th>
                                        <th>Marks Obtained (Max: {{ $exam->total_marks }})</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($students as $student)
                                    <tr>
                                        <td>{{ $student->id }}</td>
                                        <td>{{ $student->name }}</td>
                                        <td>{{ $student->roll_number ?? 'N/A' }}</td>
                                        <td>
                                            <input type="hidden" name="marks[{{ $loop->index }}][student_id]" value="{{ $student->id }}">
                                            <input type="number" 
                                                   name="marks[{{ $loop->index }}][marks_obtained]" 
                                                   class="form-control" 
                                                   min="0" 
                                                   max="{{ $exam->total_marks }}"
                                                   step="0.01"
                                                   value="{{ old('marks.'.$loop->index.'.marks_obtained', $existingResults->get($student->id)?->marks_obtained) }}"
                                                   required>
                                        </td>
                                        <td>
                                            @if($existingResults->has($student->id))
                                                <span class="badge bg-success">Entered</span>
                                            @else
                                                <span class="badge bg-warning">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No students found for this class.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">Save All Marks</button>
                            <a href="{{ route('teacher.results.index') }}" class="btn btn-secondary">Back to Results</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection