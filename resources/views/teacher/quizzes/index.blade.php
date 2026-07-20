@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 text-dark font-weight-bold">Online Quiz Builder</h1>
            <p class="text-secondary">Create online MCQ tests, compile quiz questions, and view exam integrations.</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <button class="btn btn-primary rounded-pill px-4 py-2 font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createQuizModal">
                Create Online Quiz
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="card-title text-dark font-weight-bold mb-0">Quiz List</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4">Quiz Title</th>
                            <th>Exam Integration</th>
                            <th class="text-center">Duration</th>
                            <th class="text-center">Questions Count</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quizzes as $quiz)
                            <tr>
                                <td class="ps-4 font-weight-bold text-dark">{{ $quiz->title }}</td>
                                <td>{{ $quiz->exam->name ?? 'None' }}</td>
                                class="text-dark font-weight-bold text-center"
                                <td class="text-center font-weight-bold text-dark">{{ $quiz->duration_minutes }} mins</td>
                                <td class="text-center font-weight-bold text-dark">{{ $quiz->total_questions }}</td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('teacher.quizzes.show', $quiz->id) }}" class="btn btn-sm btn-outline-primary px-3 rounded-pill">Manage Questions</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">No online quizzes created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Quiz Modal -->
<div class="modal fade" id="createQuizModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog text-dark">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('teacher.quizzes.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Create Online Quiz</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Quiz Title</label>
                        <input type="text" class="form-control text-dark" name="title" required placeholder="e.g. Science MCQ Test 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Associate with Exam</label>
                        <select class="form-select text-dark" name="exam_id">
                            <option value="">None (Independent Practice Test)</option>
                            @foreach($exams as $exam)
                                <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Duration (Minutes)</label>
                        <input type="number" class="form-control text-dark" name="duration_minutes" required min="1" value="30">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
