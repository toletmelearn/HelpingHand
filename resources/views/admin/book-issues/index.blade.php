@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Book Issue Management</h1>
            <p class="mb-4">Manage book issues and returns</p>
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Book Issues List</h5>
                        <a href="{{ route('admin.book-issues.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Issue New Book
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($bookIssues->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-journal-bookmark display-4 text-muted"></i>
                            <p class="mt-3 text-muted">No book issues found. Issue your first book to get started.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Student</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Fine</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookIssues as $issue)
                                        <tr>
                                            <td>
                                                <strong>{{ $issue->book->book_name }}</strong>
                                                <br><small class="text-muted">by {{ $issue->book->author }}</small>
                                            </td>
                                            <td>
                                                {{ $issue->student->first_name }} {{ $issue->student->last_name }}
                                                <br><small class="text-muted">Adm: {{ $issue->student->admission_number }}</small>
                                            </td>
                                            <td>{{ $issue->issue_date->format('M d, Y') }}</td>
                                            <td>
                                                {{ $issue->due_date->format('M d, Y') }}
                                                @if($issue->isOverdue())
                                                    <br><span class="badge bg-danger">Overdue</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($issue->status === 'issued')
                                                    <span class="badge bg-warning">Issued</span>
                                                @elseif($issue->status === 'returned')
                                                    <span class="badge bg-success">Returned</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($issue->fine_amount > 0)
                                                    <span class="text-danger">${{ $issue->fine_amount }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('admin.book-issues.show', $issue) }}" 
                                                       class="btn btn-outline-info" 
                                                       title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if($issue->status === 'issued')
                                                        <a href="{{ route('admin.library.return', $issue->id) }}" 
                                                           class="btn btn-outline-success" 
                                                           title="Return Book"
                                                           onclick="return confirm('Are you sure you want to return this book?')">
                                                            <i class="bi bi-arrow-down-circle"></i>
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('admin.book-issues.edit', $issue) }}" 
                                                       class="btn btn-outline-primary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="{{ route('admin.book-issues.destroy', $issue) }}" 
                                                       class="btn btn-outline-danger" 
                                                       title="Delete"
                                                       onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this issue record?')) document.getElementById('delete-form-{{ $issue->id }}').submit();">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <form id="delete-form-{{ $issue->id }}" 
                                                          action="{{ route('admin.book-issues.destroy', $issue) }}" 
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
                        
                        <div class="mt-3">
                            {{ $bookIssues->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
