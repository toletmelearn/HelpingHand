@extends('layouts.admin')

@section('title', 'Library OPAC Catalog Search')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    .book-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 10px;
    }
    .book-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Public OPAC Catalog Search</h1>
        <a href="{{ route('library.index') }}" class="btn btn-dark shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Return to Circulations
        </a>
    </div>

    <!-- Filters -->
    <div class="card glass-card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('library.opac') }}" method="GET" class="row align-items-end">
                <div class="col-md-5">
                    <label for="search" class="font-weight-bold text-dark">Search Title, Author, or ISBN</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" class="form-control" placeholder="Search parameters...">
                </div>
                <div class="col-md-3">
                    <label for="subject" class="font-weight-bold text-dark">Subject Area</label>
                    <select name="subject" id="subject" class="form-control">
                        <option value="">-- All Subjects --</option>
                        @foreach($subjects as $sub)
                            <option value="{{ $sub }}" {{ $subject == $sub ? 'selected' : '' }}>{{ $sub }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="class_grade" class="font-weight-bold text-dark">Class Grade</label>
                    <select name="class_grade" id="class_grade" class="form-control">
                        <option value="">-- All Grades --</option>
                        @foreach($grades as $g)
                            <option value="{{ $g }}" {{ $grade == $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-block">Find Books</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Catalog results -->
    <div class="row">
        @forelse($books as $book)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card shadow border-0 book-card h-100">
                    @if($book->cover_image)
                        <img src="{{ $book->cover_image }}" class="card-img-top" alt="{{ $book->book_name }}" style="height: 180px; object-fit: cover; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                    @else
                        <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center text-white font-weight-bold" style="height: 180px; border-top-left-radius: 10px; border-top-right-radius: 10px; font-size: 1.2rem; background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                            <i class="fas fa-book fa-2x mr-2"></i> {{ $book->subject }}
                        </div>
                    @endif
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title font-weight-bold text-gray-800 mb-1" style="font-size: 1rem;">{{ $book->book_name }}</h5>
                            <span class="text-muted d-block small mb-2">by {{ $book->author }}</span>
                            <hr class="my-2">
                            <span class="text-dark d-block small">Publisher: {{ $book->publisher }}</span>
                            <span class="text-dark d-block small">Shelf Number: <strong>{{ $book->rack_shelf_number }}</strong></span>
                        </div>
                        <div class="mt-3 d-flex align-items-center justify-content-between">
                            @if($book->available_copies > 0)
                                <span class="badge badge-success px-2 py-1">In Stock ({{ $book->available_copies }} left)</span>
                            @else
                                <span class="badge badge-danger px-2 py-1">Out of Stock</span>
                            @endif
                            <span class="text-muted small">Total: {{ $book->total_quantity }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <i class="fas fa-book-dead fa-3x text-gray-300 mb-3"></i>
                <h5 class="text-gray-500">No catalog books match your search filters.</h5>
            </div>
        @endforelse
    </div>
</div>
@endsection
