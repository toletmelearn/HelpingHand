@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Issue Book</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.book-issues.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="book_id" class="form-label">Select Book *</label>
                            <select class="form-select @error('book_id') is-invalid @enderror" 
                                    id="book_id" 
                                    name="book_id" 
                                    required>
                                <option value="">Choose a book...</option>
                                @foreach($books as $book)
                                    <option value="{{ $book->id }}" 
                                            {{ old('book_id') == $book->id ? 'selected' : '' }}
                                            data-available="{{ $book->available_copies }}">
                                        {{ $book->book_name }} by {{ $book->author }} 
                                        (Available: {{ $book->available_copies }})
                                    </option>
                                @endforeach
                            </select>
                            @error('book_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Select Student *</label>
                            <select class="form-select @error('student_id') is-invalid @enderror" 
                                    id="student_id" 
                                    name="student_id" 
                                    required>
                                <option value="">Choose a student...</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" 
                                            {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                        {{ $student->first_name }} {{ $student->last_name }} 
                                        (Adm: {{ $student->admission_number }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="issue_date" class="form-label">Issue Date *</label>
                                <input type="date" 
                                       class="form-control @error('issue_date') is-invalid @enderror" 
                                       id="issue_date" 
                                       name="issue_date" 
                                       value="{{ old('issue_date', now()->format('Y-m-d')) }}" 
                                       required>
                                @error('issue_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="due_date" 
                                       name="due_date" 
                                       value="{{ old('due_date', now()->addDays(14)->format('Y-m-d')) }}" 
                                       readonly>
                                <div class="form-text">Due date is automatically calculated based on library settings</div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.book-issues.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Issues
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-up-circle"></i> Issue Book
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookSelect = document.getElementById('book_id');
    const dueDateInput = document.getElementById('due_date');
    const issueDateInput = document.getElementById('issue_date');
    
    bookSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const availableCopies = selectedOption.dataset.available;
        
        if (availableCopies == 0) {
            alert('No copies available for this book!');
            this.value = '';
        }
    });
    
    issueDateInput.addEventListener('change', function() {
        const issueDate = new Date(this.value);
        const dueDate = new Date(issueDate);
        dueDate.setDate(dueDate.getDate() + 14); // Default 14 days
        dueDateInput.value = dueDate.toISOString().split('T')[0];
    });
});
</script>
@endpush
@endsection
