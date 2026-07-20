@extends('layouts.teacher')

@section('title', 'Edit Exam Paper')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Exam Paper</h3>
                </div>
                <!-- /.card-header -->
                <form action="{{ route('teacher.exam-papers.update', $examPaper->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" id="title" class="form-control" 
                                           value="{{ old('title', $examPaper->title) }}" required>
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
                                            <option value="{{ $exam->id }}" {{ old('exam_id', $examPaper->exam_id) == $exam->id ? 'selected' : '' }}>
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
                                            <option value="{{ $class->id }}" {{ old('class_id', $examPaper->class_id) == $class->id ? 'selected' : '' }}>
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
                                    <label for="subject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" id="subject" class="form-control" 
                                           value="{{ old('subject', $examPaper->subject) }}" required>
                                    @error('subject')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="instructions">Instructions</label>
                            <textarea name="instructions" id="instructions" class="form-control" rows="3">{{ old('instructions', $examPaper->instructions) }}</textarea>
                            @error('instructions')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="paper_content">Paper Content</label>
                            <textarea name="paper_content" id="paper_content" class="form-control" rows="10">{{ old('paper_content', $examPaper->paper_content) }}</textarea>
                            @error('paper_content')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="paper_file">Upload File (PDF, DOC, DOCX)</label>
                            <input type="file" name="paper_file" id="paper_file" class="form-control" accept=".pdf,.doc,.docx">
                            <small class="form-text text-muted">Maximum file size: 2MB</small>
                            @if($examPaper->file_path)
                                <p class="mt-2">
                                    Current file: <a href="{{ asset('storage/'.$examPaper->file_path) }}" target="_blank">{{ basename($examPaper->file_path) }}</a>
                                </p>
                            @endif
                            @error('paper_file')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Update Paper</button>
                        <a href="{{ route('teacher.exam-papers.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection