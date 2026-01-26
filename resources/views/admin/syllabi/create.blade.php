@extends('layouts.app')

@section('title', 'Create Syllabus')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Create Syllabus</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.syllabi.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-control @error('subject') is-invalid @enderror" 
                                            id="subject" name="subject" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject }}" {{ old('subject') == $subject ? 'selected' : '' }}>
                                                {{ $subject }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="class_name" class="form-label">Class *</label>
                                    <select class="form-control @error('class_name') is-invalid @enderror" 
                                            id="class_name" name="class_name" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class }}" {{ old('class_name') == $class ? 'selected' : '' }}>
                                                {{ $class }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="section" class="form-label">Section *</label>
                                    <select class="form-control @error('section') is-invalid @enderror" 
                                            id="section" name="section" required>
                                        <option value="">Select Section</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section }}" {{ old('section') == $section ? 'selected' : '' }}>
                                                {{ $section }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" maxlength="255" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                           id="start_date" name="start_date" value="{{ old('start_date') }}">
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="total_duration_hours" class="form-label">Total Duration (Hours)</label>
                                    <input type="number" class="form-control @error('total_duration_hours') is-invalid @enderror" 
                                           id="total_duration_hours" name="total_duration_hours" value="{{ old('total_duration_hours') }}" step="0.01" min="0">
                                    @error('total_duration_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="4">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                           id="end_date" name="end_date" value="{{ old('end_date') }}">
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="academic_session" class="form-label">Academic Session</label>
                                    <input type="text" class="form-control @error('academic_session') is-invalid @enderror" 
                                           id="academic_session" name="academic_session" value="{{ old('academic_session', config('app.academic_session', date('Y').'-'.(date('Y')+1))) }}" placeholder="e.g. 2024-2025">
                                    @error('academic_session')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control @error('status') is-invalid @enderror" 
                                            id="status" name="status">
                                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chapters Section -->
                        <div class="mb-3">
                            <label class="form-label">Chapters</label>
                            <div id="chapters-container">
                                @if(old('chapters'))
                                    @foreach(old('chapters') as $index => $chapter)
                                        <div class="chapter-row row mb-2" data-index="{{ $index }}">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="chapters[{{ $index }}][title]" 
                                                       value="{{ $chapter['title'] ?? '' }}" placeholder="Chapter Title">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" class="form-control" name="chapters[{{ $index }}][description]" 
                                                       value="{{ $chapter['description'] ?? '' }}" placeholder="Description">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control" name="chapters[{{ $index }}][duration_hours]" 
                                                       value="{{ $chapter['duration_hours'] ?? '' }}" step="0.01" min="0" placeholder="Hours">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-danger btn-sm remove-chapter" onclick="removeChapter(this)">-</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="chapter-row row mb-2" data-index="0">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" name="chapters[0][title]" 
                                                   placeholder="Chapter Title">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="chapters[0][description]" 
                                                   placeholder="Description">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control" name="chapters[0][duration_hours]" 
                                                   step="0.01" min="0" placeholder="Hours">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm remove-chapter" onclick="removeChapter(this)">-</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addChapter()">+ Add Chapter</button>
                        </div>
                        
                        <!-- Learning Objectives Section -->
                        <div class="mb-3">
                            <label for="learning_objectives" class="form-label">Learning Objectives</label>
                            <textarea class="form-control @error('learning_objectives') is-invalid @enderror" 
                                      id="learning_objectives" name="learning_objectives" rows="3">{{ old('learning_objectives') }}</textarea>
                            @error('learning_objectives')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Assessment Criteria Section -->
                        <div class="mb-3">
                            <label for="assessment_criteria" class="form-label">Assessment Criteria</label>
                            <textarea class="form-control @error('assessment_criteria') is-invalid @enderror" 
                                      id="assessment_criteria" name="assessment_criteria" rows="3">{{ old('assessment_criteria') }}</textarea>
                            @error('assessment_criteria')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.syllabi.index') }}" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Syllabus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let chapterIndex = {{ old('chapters') ? count(old('chapters')) : 1 }};
        
        function addChapter() {
            const container = document.getElementById('chapters-container');
            const newRow = document.createElement('div');
            newRow.className = 'chapter-row row mb-2';
            newRow.setAttribute('data-index', chapterIndex);
            newRow.innerHTML = `
                <div class="col-md-5">
                    <input type="text" class="form-control" name="chapters[${chapterIndex}][title]" 
                           placeholder="Chapter Title">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="chapters[${chapterIndex}][description]" 
                           placeholder="Description">
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="chapters[${chapterIndex}][duration_hours]" 
                           step="0.01" min="0" placeholder="Hours">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-chapter" onclick="removeChapter(this)">-</button>
                </div>
            `;
            container.appendChild(newRow);
            chapterIndex++;
        }
        
        function removeChapter(button) {
            const row = button.closest('.chapter-row');
            row.remove();
        }
    </script>
</div>
@endsection