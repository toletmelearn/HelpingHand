@extends('layouts.student')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Online MCQ Quizzes</h1>
            <p class="mb-4">Access assigned quizzes and test your knowledge directly on the student portal.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assigned Quizzes</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Quiz Title</th>
                                    <th>Exam Association</th>
                                    <th>Duration</th>
                                    <th>Total Questions</th>
                                    <th>Status / Score</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quizzes as $quiz)
                                    <tr>
                                        <td><strong>{{ $quiz->title }}</strong></td>
                                        <td>{{ $quiz->exam ? $quiz->exam->name : 'General Practice Quiz' }}</td>
                                        <td>{{ $quiz->duration_minutes }} Mins</td>
                                        <td>{{ $quiz->total_questions }} Questions</td>
                                        <td>
                                            @if(isset($submissions[$quiz->id]))
                                                <span class="badge bg-success">Completed</span>
                                                <br><small class="text-success font-weight-bold">Score: {{ $submissions[$quiz->id]->score }} / {{ $quiz->total_questions }}</small>
                                            @else
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!isset($submissions[$quiz->id]))
                                                <a href="{{ route('student.quizzes.take', $quiz->id) }}" class="btn btn-primary btn-sm">Take Quiz</a>
                                            @else
                                                <button class="btn btn-secondary btn-sm" disabled>Taken</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No online quizzes assigned to your class.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
