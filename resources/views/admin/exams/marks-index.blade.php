@extends('layouts.admin')

@section('title', 'Uploaded Marks - Examination')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-line"></i> Uploaded Marks
                    </h4>
                    <p class="mb-0">View all marks uploaded by teachers</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Student</th>
                                    <th>Roll Number</th>
                                    <th>Exam</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Date Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($marks as $mark)
                                    <tr>
                                        <td>{{ $mark->class_name }}</td>
                                        <td>{{ $mark->subject_name }}</td>
                                        <td>{{ $mark->teacher_name }}</td>
                                        <td>{{ $mark->student_name }}</td>
                                        <td>{{ $mark->roll_number }}</td>
                                        <td>{{ $mark->exam_name }}</td>
                                        <td>{{ $mark->marks_obtained }}</td>
                                        <td>{{ $mark->total_marks }}</td>
                                        <td>{{ number_format($mark->percentage, 2) }}%</td>
                                        <td>
                                            <span class="badge bg-{{ $mark->grade == 'F' ? 'danger' : 'success' }}">
                                                {{ $mark->grade }}
                                            </span>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($mark->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center">No marks uploaded yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        {{ $marks->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection