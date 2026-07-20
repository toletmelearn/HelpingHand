@extends('layouts.parent')

@section('title', 'Homework Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Homework Details</h4>
                    <a href="{{ route('parent.homework.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Homework
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Subject:</strong>
                            <p>{{ $homeworkNotice->subject->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Class:</strong>
                            <p>{{ $homeworkNotice->schoolClass->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Assigned By:</strong>
                            <p>{{ $homeworkNotice->teacherLogin->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Due Date:</strong>
                            <p>{{ $homeworkNotice->due_date ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Title:</strong>
                            <p>{{ $homeworkNotice->title }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <p>
                                <span class="badge bg-{{ $homeworkNotice->status === 'active' ? 'success' : ($homeworkNotice->status === 'inactive' ? 'secondary' : 'info') }}">
                                    {{ ucfirst($homeworkNotice->status) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <div class="border p-3 bg-light">
                            {!! nl2br(e($homeworkNotice->description)) !!}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Priority:</strong>
                        <p>
                            <span class="badge bg-{{ $homeworkNotice->priority === 'high' ? 'danger' : ($homeworkNotice->priority === 'medium' ? 'warning' : 'success') }}">
                                {{ ucfirst($homeworkNotice->priority) }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection