@extends('layouts.admin')

@section('title', 'Edit Exam Paper')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Exam Paper - {{ $examPaper->title }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exam-papers.update', $examPaper) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title', $examPaper->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                           id="subject" name="subject" value="{{ old('subject', $examPaper->subject) }}" required>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="class_section" class="form-label">Class Section *</label>
                                    <input type="text" class="form-control @error('class_section') is-invalid @enderror" 
                                           id="class_section" name="class_section" value="{{ old('class_section', $examPaper->class_section) }}" required>
                                    @error('class_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type *</label>
                                    <select class="form-select @error('exam_type') is-invalid @enderror" 
                                            id="exam_type" name="exam_type" required>
                                        <option value="">Select Exam Type</option>
                                        <option value="Mid-term" {{ old('exam_type', $examPaper->exam_type) == 'Mid-term' ? 'selected' : '' }}>Mid-term</option>
                                        <option value="Final" {{ old('exam_type', $examPaper->exam_type) == 'Final' ? 'selected' : '' }}>Final</option>
                                        <option value="Unit Test" {{ old('exam_type', $examPaper->exam_type) == 'Unit Test' ? 'selected' : '' }}>Unit Test</option>
                                        <option value="Quiz" {{ old('exam_type', $examPaper->exam_type) == 'Quiz' ? 'selected' : '' }}>Quiz</option>
                                        <option value="Assignment" {{ old('exam_type', $examPaper->exam_type) == 'Assignment' ? 'selected' : '' }}>Assignment</option>
                                        <option value="Project" {{ old('exam_type', $examPaper->exam_type) == 'Project' ? 'selected' : '' }}>Project</option>
                                        <option value="Practical" {{ old('exam_type', $examPaper->exam_type) == 'Practical' ? 'selected' : '' }}>Practical</option>
                                    </select>
                                    @error('exam_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="paper_type" class="form-label">Paper Type *</label>
                                    <select class="form-select @error('paper_type') is-invalid @enderror" 
                                            id="paper_type" name="paper_type" required>
                                        <option value="">Select Paper Type</option>
                                        <option value="Question Paper" {{ old('paper_type', $examPaper->paper_type) == 'Question Paper' ? 'selected' : '' }}>Question Paper</option>
                                        <option value="Answer Key" {{ old('paper_type', $examPaper->paper_type) == 'Answer Key' ? 'selected' : '' }}>Answer Key</option>
                                        <option value="Solution" {{ old('paper_type', $examPaper->paper_type) == 'Solution' ? 'selected' : '' }}>Solution</option>
                                        <option value="Syllabus" {{ old('paper_type', $examPaper->paper_type) == 'Syllabus' ? 'selected' : '' }}>Syllabus</option>
                                        <option value="Sample Paper" {{ old('paper_type', $examPaper->paper_type) == 'Sample Paper' ? 'selected' : '' }}>Sample Paper</option>
                                        <option value="Previous Year" {{ old('paper_type', $examPaper->paper_type) == 'Previous Year' ? 'selected' : '' }}>Previous Year</option>
                                    </select>
                                    @error('paper_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_id" class="form-label">Related Exam</label>
                                    <select class="form-select @error('exam_id') is-invalid @enderror" 
                                            id="exam_id" name="exam_id">
                                        <option value="">Select Exam (Optional)</option>
                                        @foreach($exams as $exam)
                                            <option value="{{ $exam->id }}" {{ old('exam_id', $examPaper->exam_id) == $exam->id ? 'selected' : '' }}>
                                                {{ $exam->name }} ({{ $exam->class_name }} - {{ $exam->subject }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="template_id" class="form-label">Use Template</label>
                                    <select class="form-select @error('template_id') is-invalid @enderror" 
                                            id="template_id" name="template_id">
                                        <option value="">Select Template (Optional)</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template->id }}" {{ old('template_id', $examPaper->template_used ? $template->id : '') == $template->id ? 'selected' : '' }}>
                                                {{ $template->name }} ({{ $template->subject }} - {{ $template->class_section }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('template_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="file" class="form-label">Upload New File (Optional)</label>
                                    <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                           id="file" name="file">
                                    <div class="form-text">Supported formats: PDF, DOC, DOCX, TXT. Max size: 10MB</div>
                                    @if($examPaper->file_path)
                                        <div class="mt-2">
                                            <small class="text-muted">Current file: <a href="{{ Storage::url($examPaper->file_path) }}" target="_blank">{{ $examPaper->file_name }}</a></small>
                                        </div>
                                    @endif
                                    @error('file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Instructions</label>
                                    <textarea class="form-control @error('instructions') is-invalid @enderror" 
                                              id="instructions" name="instructions" rows="4">{{ old('instructions', $examPaper->instructions) }}</textarea>
                                    @error('instructions')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Status: 
                                        <span class="badge bg-{{ 
                                            $examPaper->status == 'draft' ? 'secondary' : 
                                            ($examPaper->status == 'submitted' ? 'warning' : 
                                            ($examPaper->status == 'approved' ? 'success' : 
                                            ($examPaper->status == 'locked' ? 'dark' : 'danger'))) 
                                        }}">{{ ucfirst($examPaper->status) }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Online Question Editor -->
                        <div class="mb-3">
                            <h5>Online Question Editor</h5>
                            <p class="text-muted">Add questions directly in the system (optional)</p>
                            <button type="button" class="btn btn-sm btn-success" onclick="addQuestion()">Add Question</button>
                        </div>
                        
                        <div id="questions-container">
                            <!-- Existing questions -->
                            @if($examPaper->questions_data)
                                @php $index = 0; @endphp
                                @foreach($examPaper->questions_data as $question)
                                <div class="card mb-3" id="question-{{ $index }}">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6>Question {{ $index + 1 }}</h6>
                                        <button type="button" class="btn btn-sm btn-danger remove-question-btn" data-index="{{ $index }}">Remove</button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label">Question Text</label>
                                                    <textarea name="questions_data[{{ $index }}][text]" class="form-control" rows="3">{{ $question['text'] ?? '' }}</textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Marks</label>
                                                    <input type="number" name="questions_data[{{ $index }}][marks]" class="form-control" min="1" value="{{ $question['marks'] ?? 1 }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Question Type</label>
                                                    <select name="questions_data[{{ $index }}][type]" class="form-select">
                                                        <option value="short-answer" {{ ($question['type'] ?? '') == 'short-answer' ? 'selected' : '' }}>Short Answer</option>
                                                        <option value="long-answer" {{ ($question['type'] ?? '') == 'long-answer' ? 'selected' : '' }}>Long Answer</option>
                                                        <option value="mcq" {{ ($question['type'] ?? '') == 'mcq' ? 'selected' : '' }}>MCQ</option>
                                                        <option value="case-based" {{ ($question['type'] ?? '') == 'case-based' ? 'selected' : '' }}>Case Based</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php $index++; @endphp
                                @endforeach
                            @endif
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Update Exam Paper</button>
                            <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden input to pass the initial count -->
<input type="hidden" id="initialQuestionCount" value="{{ count($examPaper->questions_data ?? []) }}" />

<script>
// Get the initial question count from the hidden input
var initialQuestionCount = parseInt(document.getElementById('initialQuestionCount').value);
var questionIndex = initialQuestionCount;

function addQuestion() {
    var container = document.getElementById('questions-container');
    
    var questionDiv = document.createElement('div');
    questionDiv.className = 'card mb-3';
    questionDiv.id = 'question-' + questionIndex;
    
    // Build HTML string piece by piece to avoid parsing issues
    var html = '<div class="card-header d-flex justify-content-between align-items-center">';
    html += '<h6>Question ' + (questionIndex + 1) + '</h6>';
    html += '<button type="button" class="btn btn-sm btn-danger remove-question-btn" data-index="' + questionIndex + '">Remove</button>';
    html += '</div>';
    html += '<div class="card-body">';
    html += '<div class="row">';
    html += '<div class="col-md-8">';
    html += '<div class="mb-3">';
    html += '<label class="form-label">Question Text</label>';
    html += '<textarea name="questions_data[' + questionIndex + '][text]" class="form-control" rows="3"></textarea>';
    html += '</div>';
    html += '</div>';
    html += '<div class="col-md-4">';
    html += '<div class="mb-3">';
    html += '<label class="form-label">Marks</label>';
    html += '<input type="number" name="questions_data[' + questionIndex + '][marks]" class="form-control" min="1" value="1">';
    html += '</div>';
    html += '<div class="mb-3">';
    html += '<label class="form-label">Question Type</label>';
    html += '<select name="questions_data[' + questionIndex + '][type]" class="form-select">';
    html += '<option value="short-answer">Short Answer</option>';
    html += '<option value="long-answer">Long Answer</option>';
    html += '<option value="mcq">MCQ</option>';
    html += '<option value="case-based">Case Based</option>';
    html += '</select>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    
    questionDiv.innerHTML = html;
    container.appendChild(questionDiv);
    questionIndex++;
}

function removeQuestion(index) {
    var questionElement = document.getElementById('question-' + index);
    if (questionElement) {
        questionElement.remove();
    }
}

// Add event listeners after DOM is loaded to handle dynamically added buttons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-question-btn')) {
        e.preventDefault();
        var index = e.target.getAttribute('data-index');
        removeQuestion(parseInt(index));
    }
});
</script>
@endsection
