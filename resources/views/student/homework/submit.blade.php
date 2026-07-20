@extends('layouts.student')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <a href="{{ route('student.homework.index') }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Homework Board
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h3 class="card-title text-dark font-weight-bold mb-0">Submit Homework</h3>
                    <p class="text-secondary mb-0">Upload your homework assignment document or image files for teacher evaluation.</p>
                </div>
                <div class="card-body p-4">
                    <div class="p-3 bg-light rounded text-dark mb-4 border-start border-primary border-4">
                        <h6 class="font-weight-bold mb-1">{{ $homework->title }}</h6>
                        <p class="mb-1" style="font-size: 0.95rem;">{{ $homework->description }}</p>
                        <small class="text-secondary d-block mt-2">Due Date: {{ \Carbon\Carbon::parse($homework->due_date)->format('M d, Y') }}</small>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('student.homework.store-submission', $homework->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label text-secondary font-weight-bold">Select File Attachment</label>
                            <input type="file" class="form-control text-dark" id="file" name="file" required>
                            <small class="text-secondary d-block mt-1">Accepted Formats: PDF, PNG, JPG, JPEG, DOCX. Max Size: 5MB</small>
                        </div>

                        @if($submission && $submission->file_path)
                            <div class="p-3 bg-info-light text-info rounded mb-4 d-flex align-items-center" style="background-color: #e0f7fa;">
                                <i class="bi bi-file-earmark-check me-2" style="font-size: 1.5rem;"></i>
                                <div>
                                    <span class="d-block text-dark font-weight-bold">Currently Uploaded File</span>
                                    <a href="{{ asset('storage/' . $submission->file_path) }}" target="_blank" class="text-decoration-none font-weight-bold">View Submitted File</a>
                                </div>
                            </div>
                        @endif

                        <div class="mb-4">
                            <label for="student_notes" class="form-label text-secondary font-weight-bold">Student Notes / Message</label>
                            <textarea class="form-control text-dark" id="student_notes" name="student_notes" rows="4" placeholder="Write any message or notes for the teacher..."></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill font-weight-bold shadow-sm">
                                {{ $submission ? 'Re-submit Homework' : 'Submit Homework' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
