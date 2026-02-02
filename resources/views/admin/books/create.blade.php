@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add New Book</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.books.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="book_name" class="form-label">Book Name *</label>
                                <input type="text" 
                                       class="form-control @error('book_name') is-invalid @enderror" 
                                       id="book_name" 
                                       name="book_name" 
                                       value="{{ old('book_name') }}" 
                                       required>
                                @error('book_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" 
                                       class="form-control @error('isbn') is-invalid @enderror" 
                                       id="isbn" 
                                       name="isbn" 
                                       value="{{ old('isbn') }}">
                                @error('isbn')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label">Author *</label>
                                <input type="text" 
                                       class="form-control @error('author') is-invalid @enderror" 
                                       id="author" 
                                       name="author" 
                                       value="{{ old('author') }}" 
                                       required>
                                @error('author')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="publisher" class="form-label">Publisher *</label>
                                <input type="text" 
                                       class="form-control @error('publisher') is-invalid @enderror" 
                                       id="publisher" 
                                       name="publisher" 
                                       value="{{ old('publisher') }}" 
                                       required>
                                @error('publisher')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" 
                                       class="form-control @error('subject') is-invalid @enderror" 
                                       id="subject" 
                                       name="subject" 
                                       value="{{ old('subject') }}" 
                                       required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="class_grade" class="form-label">Class/Grade *</label>
                                <input type="text" 
                                       class="form-control @error('class_grade') is-invalid @enderror" 
                                       id="class_grade" 
                                       name="class_grade" 
                                       value="{{ old('class_grade') }}" 
                                       required>
                                @error('class_grade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="total_quantity" class="form-label">Total Quantity *</label>
                                <input type="number" 
                                       class="form-control @error('total_quantity') is-invalid @enderror" 
                                       id="total_quantity" 
                                       name="total_quantity" 
                                       value="{{ old('total_quantity') }}" 
                                       min="1" 
                                       required>
                                @error('total_quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="rack_shelf_number" class="form-label">Rack/Shelf Number *</label>
                                <input type="text" 
                                       class="form-control @error('rack_shelf_number') is-invalid @enderror" 
                                       id="rack_shelf_number" 
                                       name="rack_shelf_number" 
                                       value="{{ old('rack_shelf_number') }}" 
                                       required>
                                @error('rack_shelf_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cover_image" class="form-label">Book Cover Image</label>
                            <input type="file" 
                                   class="form-control @error('cover_image') is-invalid @enderror" 
                                   id="cover_image" 
                                   name="cover_image" 
                                   accept="image/*">
                            <div class="form-text">Allowed formats: JPEG, PNG, JPG, GIF. Max size: 2MB</div>
                            @error('cover_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.books.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Books
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Book
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
