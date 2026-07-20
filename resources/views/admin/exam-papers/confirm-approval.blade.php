@extends('layouts.admin')

@section('title', 'Confirm Approval')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Confirm Approval</h3>
                </div>
                <div class="card-body">
                    <p>Are you sure you want to approve this exam paper?</p>
                    
                    <div class="mb-3">
                        <strong>Title:</strong> {{ $examPaper->title }}
                    </div>
                    
                    <div class="mb-3">
                        <strong>Subject:</strong> {{ $examPaper->subject }}
                    </div>
                    
                    <div class="mb-3">
                        <strong>Class:</strong> {{ $examPaper->class->name ?? 'N/A' }}
                    </div>
                    
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge badge-{{ $examPaper->status == 'submitted' ? 'info' : 'warning' }}">
                            {{ ucfirst($examPaper->status) }}
                        </span>
                    </div>
                    
                    <form method="POST" action="{{ route('admin.exam-papers.approve.id', ['id' => $examPaper->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-success">Yes, Approve</button>
                        <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection