@extends('layouts.admin')

@section('title', 'Edit Exam Paper Template - ' . $examPaperTemplate->name)

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Exam Paper Template - {{ $examPaperTemplate->name }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exam-paper-templates.update', $examPaperTemplate) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Template Name *</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $examPaperTemplate->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                           id="subject" name="subject" value="{{ old('subject', $examPaperTemplate->subject) }}" required>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="class_section" class="form-label">Class Section *</label>
                                    <input type="text" class="form-control @error('class_section') is-invalid @enderror" 
                                           id="class_section" name="class_section" value="{{ old('class_section', $examPaperTemplate->class_section) }}" required>
                                    @error('class_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <input type="text" class="form-control @error('academic_year') is-invalid @enderror" 
                                           id="academic_year" name="academic_year" value="{{ old('academic_year', $examPaperTemplate->academic_year) }}">
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="is_active" class="form-label">Status</label>
                                    <select class="form-select @error('is_active') is-invalid @enderror" 
                                            id="is_active" name="is_active">
                                        <option value="1" {{ old('is_active', $examPaperTemplate->is_active) == 1 ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ old('is_active', $examPaperTemplate->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="header_content" class="form-label">Header Content</label>
                                    <textarea class="form-control @error('header_content') is-invalid @enderror" 
                                              id="header_content" name="header_content" rows="3">{{ old('header_content', $examPaperTemplate->header_content) }}</textarea>
                                    @error('header_content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instruction_block" class="form-label">Instruction Block</label>
                                    <textarea class="form-control @error('instruction_block') is-invalid @enderror" 
                                              id="instruction_block" name="instruction_block" rows="4">{{ old('instruction_block', $examPaperTemplate->instruction_block) }}</textarea>
                                    @error('instruction_block')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="footer_content" class="form-label">Footer Content</label>
                                    <textarea class="form-control @error('footer_content') is-invalid @enderror" 
                                              id="footer_content" name="footer_content" rows="3">{{ old('footer_content', $examPaperTemplate->footer_content) }}</textarea>
                                    @error('footer_content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template_content" class="form-label">Template Content *</label>
                            <textarea class="form-control @error('template_content') is-invalid @enderror" 
                                      id="template_content" name="template_content" rows="10">{{ old('template_content', $examPaperTemplate->template_content) }}</textarea>
                            <div class="form-text">
                                Use placeholders like {school_name}, {exam_title}, {subject}, etc. for dynamic content.
                            </div>
                            @error('template_content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $examPaperTemplate->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Update Template</button>
                            <a href="{{ route('admin.exam-paper-templates.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
<script>
CKEDITOR.replace('template_content');
</script>
@endsection
