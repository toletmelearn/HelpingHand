@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="mb-4">
        <a href="{{ route('teacher.quizzes.index') }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Quiz List
        </a>
    </div>

    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 text-dark font-weight-bold">{{ $quiz->title }}</h1>
            <p class="text-secondary">Duration: <strong>{{ $quiz->duration_minutes }} minutes</strong> | Questions: <strong>{{ $quiz->total_questions }}</strong></p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <button class="btn btn-primary rounded-pill px-4 py-2 font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                Add Question
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
            <h5 class="card-title text-dark font-weight-bold mb-0">Quiz Questions</h5>
        </div>
        <div class="card-body p-4">
            @forelse($quiz->questions as $index => $q)
                <div class="p-3 bg-light rounded text-dark mb-3 border-start border-primary border-4">
                    <h6 class="font-weight-bold mb-2">Q{{ $index + 1 }}. {{ $q->question_text }}</h6>
                    <div class="row g-2">
                        <div class="col-md-6">A) {{ $q->option_a }}</div>
                        <div class="col-md-6">B) {{ $q->option_b }}</div>
                        <div class="col-md-6">C) {{ $q->option_c }}</div>
                        <div class="col-md-6">D) {{ $q->option_d }}</div>
                    </div>
                    <div class="mt-2 text-success font-weight-bold" style="font-size: 0.85rem;">
                        Correct Option: {{ strtoupper($q->correct_option) }}
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-secondary">
                    No questions added to this quiz yet. Click the "Add Question" button above to insert MCQ questions.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg text-dark">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('teacher.quizzes.store-question', $quiz->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Add MCQ Question</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary font-weight-bold">Question Text</label>
                        <textarea class="form-control text-dark" name="question_text" rows="3" required placeholder="Write question statement here..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Option A</label>
                            <input type="text" class="form-control text-dark" name="option_a" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Option B</label>
                            <input type="text" class="form-control text-dark" name="option_b" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Option C</label>
                            <input type="text" class="form-control text-dark" name="option_c" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Option D</label>
                            <input type="text" class="form-control text-dark" name="option_d" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-secondary font-weight-bold">Correct Option</label>
                        <select class="form-select text-dark" name="correct_option" required>
                            <option value="a">Option A</option>
                            <option value="b">Option B</option>
                            <option value="c">Option C</option>
                            <option value="d">Option D</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
