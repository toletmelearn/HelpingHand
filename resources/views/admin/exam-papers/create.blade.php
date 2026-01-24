@extends('layouts.app')

@section('title', 'Create Exam Paper')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Create Exam Paper</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exam-papers.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                           id="subject" name="subject" value="{{ old('subject') }}" required>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="class_section" class="form-label">Class Section *</label>
                                    <input type="text" class="form-control @error('class_section') is-invalid @enderror" 
                                           id="class_section" name="class_section" value="{{ old('class_section') }}" required>
                                    @error('class_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type *</label>
                                    <select class="form-select @error('exam_type') is-invalid @enderror" 
                                            id="exam_type" name="exam_type" required>
                                        <option value="">Select Exam Type</option>
                                        <option value="Mid-term" {{ old('exam_type') == 'Mid-term' ? 'selected' : '' }}>Mid-term</option>
                                        <option value="Final" {{ old('exam_type') == 'Final' ? 'selected' : '' }}>Final</option>
                                        <option value="Unit Test" {{ old('exam_type') == 'Unit Test' ? 'selected' : '' }}>Unit Test</option>
                                        <option value="Quiz" {{ old('exam_type') == 'Quiz' ? 'selected' : '' }}>Quiz</option>
                                        <option value="Assignment" {{ old('exam_type') == 'Assignment' ? 'selected' : '' }}>Assignment</option>
                                        <option value="Project" {{ old('exam_type') == 'Project' ? 'selected' : '' }}>Project</option>
                                        <option value="Practical" {{ old('exam_type') == 'Practical' ? 'selected' : '' }}>Practical</option>
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
                                        <option value="Question Paper" {{ old('paper_type') == 'Question Paper' ? 'selected' : '' }}>Question Paper</option>
                                        <option value="Answer Key" {{ old('paper_type') == 'Answer Key' ? 'selected' : '' }}>Answer Key</option>
                                        <option value="Solution" {{ old('paper_type') == 'Solution' ? 'selected' : '' }}>Solution</option>
                                        <option value="Syllabus" {{ old('paper_type') == 'Syllabus' ? 'selected' : '' }}>Syllabus</option>
                                        <option value="Sample Paper" {{ old('paper_type') == 'Sample Paper' ? 'selected' : '' }}>Sample Paper</option>
                                        <option value="Previous Year" {{ old('paper_type') == 'Previous Year' ? 'selected' : '' }}>Previous Year</option>
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
                                            <option value="{{ $exam->id }}" {{ old('exam_id') == $exam->id ? 'selected' : '' }}>
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
                                            <option value="{{ $template->id }}" {{ old('template_id') == $template->id ? 'selected' : '' }}>
                                                {{ $template->name }} ({{ $template->subject }} - {{ $template->class_section }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('template_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="file" class="form-label">Upload File (Optional)</label>
                                    <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                           id="file" name="file">
                                    <div class="form-text">Supported formats: PDF, DOC, DOCX, TXT. Max size: 10MB</div>
                                    @error('file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Instructions</label>
                                    <textarea class="form-control @error('instructions') is-invalid @enderror" 
                                              id="instructions" name="instructions" rows="4">{{ old('instructions') }}</textarea>
                                    @error('instructions')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                            <!-- Questions will be added here dynamically -->
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Create Exam Paper</button>
                            <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let questionIndex = 0;

function addQuestion() {
    const container = document.getElementById('questions-container');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'card mb-3';
    questionDiv.id = `question-${questionIndex}`;
    
    questionDiv.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6>Question ${questionIndex + 1}</h6>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeQuestion(${questionIndex})">Remove</button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="questions_data[${questionIndex}][text]" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Marks</label>
                        <input type="number" name="questions_data[${questionIndex}][marks]" class="form-control" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <select name="questions_data[${questionIndex}][type]" class="form-select">
                            <option value="short-answer">Short Answer</option>
                            <option value="long-answer">Long Answer</option>
                            <option value="mcq">MCQ</option>
                            <option value="case-based">Case Based</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(questionDiv);
    questionIndex++;
}

function removeQuestion(index) {
    const questionElement = document.getElementById(`question-${index}`);
    if (questionElement) {
        questionElement.remove();
    }
}

// Initialize with one question if needed
document.addEventListener('DOMContentLoaded', function() {
    addQuestion(); // Add one question by default
});
</script>
@endsection