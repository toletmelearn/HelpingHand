@extends('layouts.admin')

@section('title', 'Add Result')

@section('content')
<!-- Debug Info -->
<div class="alert alert-info alert-dismissible fade show" role="alert" id="debugInfo" style="display:none;">
    <strong>Debug Information:</strong>
    <div id="debugContent"></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Add New Result</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.results.store') }}" method="POST">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="student_id">Student *</label>
                            <select name="student_id" id="student_id" class="form-control @error('student_id') is-invalid @enderror" required>
                                <option value="">Select Student</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                        {{ $student->name }} ({{ $student->class_name }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="exam_id">Exam *</label>
                            <select name="exam_id" id="exam_id" class="form-control @error('exam_id') is-invalid @enderror" required>
                                <option value="">Select Exam</option>
                                @foreach($exams as $exam)
                                    <option value="{{ $exam->id }}" {{ old('exam_id') == $exam->id ? 'selected' : '' }}>
                                        {{ $exam->name }} - {{ $exam->subject }}
                                    </option>
                                @endforeach
                            </select>
                            @error('exam_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="subject">Subject *</label>
                            <select name="subject" id="subject" class="form-control @error('subject') is-invalid @enderror" required>
                                <option value="">Select Subject</option>
                                <!-- Will be populated dynamically based on selected exam -->
                            </select>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Subject will auto-load from selected exam</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="marks_obtained">Marks Obtained *</label>
                                    <input type="number" name="marks_obtained" id="marks_obtained" class="form-control @error('marks_obtained') is-invalid @enderror" value="{{ old('marks_obtained') }}" min="0" step="0.01" required>
                                    @error('marks_obtained')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="total_marks">Total Marks *</label>
                                    <input type="number" name="total_marks" id="total_marks" class="form-control @error('total_marks') is-invalid @enderror" value="{{ old('total_marks') }}" min="0" step="0.01" required>
                                    @error('total_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="academic_year">Academic Year *</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control @error('academic_year') is-invalid @enderror" value="{{ old('academic_year', date('Y')) }}" required>
                            @error('academic_year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="term">Term *</label>
                            <input type="text" name="term" id="term" class="form-control @error('term') is-invalid @enderror" value="{{ old('term') }}" required>
                            @error('term')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>



                        <div class="form-group mb-3">
                            <label for="comments">Comments</label>
                            <textarea name="comments" id="comments" class="form-control @error('comments') is-invalid @enderror" rows="3">{{ old('comments') }}</textarea>
                            @error('comments')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Add Result</button>
                            <button type="button" class="btn btn-info" id="previewBtn" disabled>
                                <i class="bi bi-eye"></i> Preview Format
                            </button>
                            <a href="{{ route('admin.results.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Test if script is loading at all
console.log("=== SCRIPT LOADING TEST ===");
console.log("RESULT PAGE JS LOADED");

// Test if DOM is ready
if (document.readyState === "loading") {
    console.log("DOM still loading...");
    document.addEventListener("DOMContentLoaded", initScript);
} else {
    console.log("DOM already ready, initializing...");
    initScript();
}

function initScript() {
    console.log("=== INITIALIZING SCRIPT ===");
    console.log("JS working");
    console.log("CSRF Token:", document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    const examDropdown = document.getElementById("exam_id");
    const subjectDropdown = document.getElementById("subject");
    const totalMarksField = document.getElementById("total_marks");

    console.log("Elements found:", {
        examDropdown: !!examDropdown,
        subjectDropdown: !!subjectDropdown,
        totalMarksField: !!totalMarksField
    });

    if (!examDropdown) {
        console.log("Exam dropdown not found");
        return;
    }

    // Enable preview button when form is partially filled
    const previewBtn = document.getElementById("previewBtn");
    const studentDropdown = document.getElementById("student_id");
    const marksObtainedField = document.getElementById("marks_obtained");
    const termField = document.getElementById("term");
    
    function checkPreviewAvailability() {
        const hasStudent = studentDropdown && studentDropdown.value;
        const hasExam = examDropdown && examDropdown.value;
        const hasSubject = subjectDropdown && subjectDropdown.innerHTML.includes("<option");
        
        if (hasStudent && hasExam && hasSubject) {
            previewBtn.disabled = false;
            previewBtn.title = "Preview sample report card format";
        } else {
            previewBtn.disabled = true;
            previewBtn.title = "Select student, exam, and subject first";
        }
    }
    
    // Check on form changes
    [studentDropdown, examDropdown, subjectDropdown].forEach(el => {
        if (el) el.addEventListener("change", checkPreviewAvailability);
    });
    
    // Preview button click handler
    if (previewBtn) {
        previewBtn.addEventListener("click", function() {
            const studentId = studentDropdown.value;
            const examId = examDropdown.value;
            
            if (!studentId || !examId) return;
            
            // Open preview in new tab
            const previewUrl = `/admin/results/report-card/${studentId}/${examId}?preview=1`;
            window.open(previewUrl, "_blank");
        });
    }
    
    examDropdown.addEventListener("change", function () {

        console.log("Exam changed:", this.value);
        console.log("Attempting to fetch data for exam ID:", this.value);

        let examId = this.value;
        if (!examId) return;

        // Test route without auth middleware
        fetch(`/test-subject-ajax/${examId}`)
            .then(res => res.json())
            .then(data => {

                console.log("Data received:", data);

                if (data.success) {

                    subjectDropdown.innerHTML =
                        `<option value="${data.subject}" selected>${data.subject}</option>`;

                    totalMarksField.value = data.total_marks;
                    checkPreviewAvailability(); // Check if preview can be enabled
                } else {
                    console.log("Error from server:", data.message);
                    subjectDropdown.innerHTML = '<option value="">Error loading subject</option>';;
                }
            })
            .catch(err => {
                console.log("Fetch error:", err);
                subjectDropdown.innerHTML = '<option value="">Network error</option>';
            });

    });

}
</script>
@endsection
