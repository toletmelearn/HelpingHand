@extends('layouts.admin')

@section('title', 'Library Management & Circulations')

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
    .metric-card {
        border-left: 4px solid #4e73df;
        transition: transform 0.2s;
    }
    .metric-card:hover {
        transform: scale(1.02);
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Library Catalog & Circulations</h1>
        <div>
            <a href="{{ route('library.opac') }}" class="btn btn-info shadow-sm mr-2">
                <i class="fas fa-search fa-sm text-white-50"></i> Public OPAC Search
            </a>
            <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#issueBookModal">
                <i class="fas fa-book-reader fa-sm text-white-50"></i> Issue New Book
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Metrics row -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Book Stock</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalBooks }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #1cc88a;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Checkouts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalIssued }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book-reader fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #e74a3b;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Circulations</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalOverdue }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Issues listing -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Active & Past Book Issues</h6>
                    <form action="{{ route('library.index') }}" method="GET" class="form-inline">
                        <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                            <option value="">-- All Issues --</option>
                            <option value="issued" {{ $statusFilter === 'issued' ? 'selected' : '' }}>Active</option>
                            <option value="returned" {{ $statusFilter === 'returned' ? 'selected' : '' }}>Returned</option>
                            <option value="overdue" {{ $statusFilter === 'overdue' ? 'selected' : '' }}>Overdue</option>
                        </select>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search student/book..." class="form-control form-control-sm">
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Student</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($issues as $issue)
                                    <tr>
                                        <td>
                                            <strong>{{ $issue->book->book_name }}</strong>
                                            <span class="text-muted d-block small">ISBN: {{ $issue->book->isbn ?: 'N/A' }}</span>
                                        </td>
                                        <td>{{ $issue->student->first_name }} {{ $issue->student->last_name }}</td>
                                        <td>{{ $issue->issue_date->format('Y-m-d') }}</td>
                                        <td>{{ $issue->due_date->format('Y-m-d') }}</td>
                                        <td>
                                            @if($issue->status === 'returned')
                                                <span class="badge badge-success">Returned ({{ $issue->return_date->format('Y-m-d') }})</span>
                                            @elseif($issue->isOverdue())
                                                <span class="badge badge-danger">Overdue ({{ $issue->due_date->diffInDays(now()) }} days)</span>
                                            @else
                                                <span class="badge badge-primary">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($issue->fine_amount > 0)
                                                <strong class="text-danger">${{ $issue->fine_amount }}</strong>
                                            @else
                                                <span class="text-muted">$0.00</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($issue->status === 'issued')
                                                <form action="{{ route('library.return-book', $issue->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success">
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">Cleared</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No book circulation records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Library configuration settings -->
        <div class="col-lg-4">
            <div class="card glass-card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-dark">Circulation Rules Settings</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('library.settings') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="default_issue_days" class="text-dark font-weight-bold">Max Allowed Books Limit</label>
                            <input type="number" name="default_issue_days" id="default_issue_days" value="{{ $settings->default_issue_days }}" class="form-control" required min="1">
                            <small class="text-muted">Limits checkout quantity per student.</small>
                        </div>

                        <div class="form-group">
                            <label for="fine_per_day" class="text-dark font-weight-bold">Late Fine Penalty (per day)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" step="0.01" name="fine_per_day" id="fine_per_day" value="{{ $settings->fine_per_day }}" class="form-control" required min="0">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-block">Save Rule Configurations</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Issue Book Modal -->
<div class="modal fade" id="issueBookModal" tabindex="-1" role="dialog" aria-labelledby="issueBookModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="issueBookModalLabel">Issue Book To Student</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('library.issue') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_book_id" class="text-dark font-weight-bold">Select Catalog Book</label>
                        <select name="book_id" id="modal_book_id" class="form-control" required>
                            <option value="">-- Choose Book --</option>
                            @foreach($books as $book)
                                @if($book->available_copies > 0)
                                    <option value="{{ $book->id }}">{{ $book->book_name }} (Author: {{ $book->author }}, Available: {{ $book->available_copies }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_student_id" class="text-dark font-weight-bold">Select Recipient Student</label>
                        <select name="student_id" id="modal_student_id" class="form-control" required>
                            <option value="">-- Choose Student --</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} (Admission No: {{ $student->admission_no ?: 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_due_date" class="text-dark font-weight-bold">Return Due Date</label>
                        <input type="date" name="due_date" id="modal_due_date" value="{{ \Carbon\Carbon::today()->addDays($settings->default_issue_days)->format('Y-m-d') }}" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Check Out Book</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
