@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Library Reports</h1>
            <p class="mb-4">Library statistics and reports</p>
            
            <div class="mb-4">
                <a href="{{ route('library.export', 'excel') }}" class="btn btn-success me-2">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </a>
                <a href="{{ route('library.export', 'pdf') }}" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Issued Books</h5>
                    <p class="card-text display-4">{{ $totalIssued }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Returned Books</h5>
                    <p class="card-text display-4">{{ $totalReturned }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Overdue Books</h5>
                    <p class="card-text display-4">{{ $totalOverdue }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fine Collection -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Fine Collection</h5>
                </div>
                <div class="card-body text-center">
                    <h2 class="text-success">${{ $fineCollection }}</h2>
                    <p class="text-muted">Total fine collected from overdue returns</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Return Rate</h5>
                </div>
                <div class="card-body text-center">
                    @if($totalIssued > 0)
                        <h2 class="text-info">{{ round(($totalReturned / $totalIssued) * 100, 2) }}%</h2>
                    @else
                        <h2 class="text-info">0%</h2>
                    @endif
                    <p class="text-muted">Percentage of books returned</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Most Issued Books -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Most Issued Books</h5>
                </div>
                <div class="card-body">
                    @if($mostIssuedBooks->isEmpty())
                        <div class="text-center py-3">
                            <i class="bi bi-book text-muted display-4"></i>
                            <p class="mt-2 text-muted">No books have been issued yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>Total Issues</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mostIssuedBooks as $index => $book)
                                        <tr>
                                            <td><span class="badge bg-primary">{{ $index + 1 }}</span></td>
                                            <td>{{ $book->book_name }}</td>
                                            <td>{{ $book->author }}</td>
                                            <td>{{ $book->total_issues }}</td>
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
