@extends('layouts.teacher')

@section('title', 'Create Exam Paper')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create New Exam Paper</h3>
                </div>
                <!-- /.card-header -->
                <form action="{{ route('teacher.exam-papers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" id="title" class="form-control" 
                                           value="{{ old('title') }}" required>
                                    @error('title')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="exam_id">Exam <span class="text-danger">*</span></label>
                                    <select name="exam_id" id="exam_id" class="form-control" required>
                                        <option value="">Select Exam</option>
                                        @foreach($exams as $exam)
                                            <option value="{{ $exam->id }}" {{ old('exam_id') == $exam->id ? 'selected' : '' }}>
                                                {{ $exam->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="class_id">Class <span class="text-danger">*</span></label>
                                    <select name="class_id" id="class_id" class="form-control" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="class_section">Section</label>
                                    <input type="text" name="class_section" id="class_section" class="form-control" 
                                           value="{{ old('class_section') }}" placeholder="Enter section (optional)">
                                    @error('class_section')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" id="subject" class="form-control" 
                                           value="{{ old('subject') }}" required>
                                    @error('subject')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="exam_type">Exam Type</label>
                                    <input type="text" name="exam_type" id="exam_type" class="form-control" 
                                           value="{{ old('exam_type', 'General') }}" placeholder="Enter exam type">
                                    @error('exam_type')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="instructions">Instructions</label>
                            <textarea name="instructions" id="instructions" class="form-control" rows="3">{{ old('instructions') }}</textarea>
                            @error('instructions')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="paper_content">Paper Content</label>
                            <textarea name="paper_content" id="paper_content" class="form-control" rows="10">{{ old('paper_content') }}</textarea>
                            @error('paper_content')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="file">Upload File (PDF, DOC, DOCX)</label>
                            <input type="file" name="file" id="file" class="form-control" accept=".pdf,.doc,.docx">
                            <small class="form-text text-muted">Maximum file size: 2MB</small>
                            @error('file')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Submit for Approval</button>
                        <a href="{{ route('teacher.exam-papers.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Initialize textarea as a rich text editor if needed
document.addEventListener('DOMContentLoaded', function() {
    const paperContent = document.getElementById('paper_content');
    if(paperContent) {
        // Add any rich text editor initialization here if needed
    }
});
</script>
@endsection