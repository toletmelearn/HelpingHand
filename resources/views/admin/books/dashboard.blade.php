@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Library Dashboard</h1>
            <p class="mb-4">Overview of library management</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Books</h5>
                    <p class="card-text display-4">{{ $totalBooks }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Copies</h5>
                    <p class="card-text display-4">{{ $totalCopies }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Issued Books</h5>
                    <p class="card-text display-4">{{ $issuedBooks }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Overdue Books</h5>
                    <p class="card-text display-4">{{ $overdueBooks }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.books.create') }}" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-book"></i> Add New Book
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.book-issues.create') }}" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-arrow-up-circle"></i> Issue Book
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.book-issues.index') }}" class="btn btn-info btn-lg w-100">
                                <i class="bi bi-list"></i> Manage Issues
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.library.reports') }}" class="btn btn-secondary btn-lg w-100">
                                <i class="bi bi-file-earmark-bar-graph"></i> Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Books -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Low Stock Books</h5>
                </div>
                <div class="card-body">
                    @if($lowStockBooks->isEmpty())
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle text-success display-4"></i>
                            <p class="mt-2 text-muted">All books are well-stocked!</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>Total Copies</th>
                                        <th>Available</th>
                                        <th>Issued</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowStockBooks as $book)
                                        <tr>
                                            <td>{{ $book->book_name }}</td>
                                            <td>{{ $book->author }}</td>
                                            <td>{{ $book->total_quantity }}</td>
                                            <td>{{ $book->available_copies }}</td>
                                            <td>{{ $book->issued_copies }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
