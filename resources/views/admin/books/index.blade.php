@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Book Management</h1>
            <p class="mb-4">Manage all library books</p>
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Books List</h5>
                        <a href="{{ route('admin.books.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Add New Book
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($books->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-book display-4 text-muted"></i>
                            <p class="mt-3 text-muted">No books found. Add your first book to get started.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Cover</th>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>Subject</th>
                                        <th>Total Copies</th>
                                        <th>Available</th>
                                        <th>Issued</th>
                                        <th>Rack/Shelf</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($books as $book)
                                        <tr>
                                            <td>
                                                @if($book->cover_image)
                                                    <img src="{{ asset('storage/' . $book->cover_image) }}" 
                                                         alt="{{ $book->book_name }}" 
                                                         class="img-thumbnail" 
                                                         style="width: 50px; height: 70px; object-fit: cover;">
                                                @else
                                                    <div class="bg-light d-flex align-items-center justify-content-center" 
                                                         style="width: 50px; height: 70px;">
                                                        <i class="bi bi-book text-muted"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $book->book_name }}</strong>
                                                @if($book->isbn)
                                                    <br><small class="text-muted">ISBN: {{ $book->isbn }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $book->author }}</td>
                                            <td>{{ $book->subject }}</td>
                                            <td>{{ $book->total_quantity }}</td>
                                            <td>
                                                <span class="badge bg-success">{{ $book->available_copies }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">{{ $book->issued_copies }}</span>
                                            </td>
                                            <td>{{ $book->rack_shelf_number }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('admin.books.show', $book) }}" 
                                                       class="btn btn-outline-info" 
                                                       title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.books.edit', $book) }}" 
                                                       class="btn btn-outline-primary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('admin.books.destroy', $book) }}" 
                                                       class="btn btn-outline-danger" 
                                                       title="Deactivate"
                                                       onclick="event.preventDefault(); if(confirm('Are you sure you want to deactivate this book?')) document.getElementById('delete-form-{{ $book->id }}').submit();">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <form id="delete-form-{{ $book->id }}" 
                                                          action="{{ route('admin.books.destroy', $book) }}" 
                                                          method="POST" 
                                                          style="display: none;">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
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
