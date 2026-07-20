@extends('layouts.admin')

@section('title', 'Homework Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-clipboard"></i> Homework Details</h4>
                    <a href="{{ route('admin.homework.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Assigned By:</strong>
                            <p>{{ $homeworkNotice->teacherLogin->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Class:</strong>
                            <p>{{ $homeworkNotice->schoolClass->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Subject:</strong>
                            <p>{{ $homeworkNotice->subject->name ?? 'N/A' }}</p>
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
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Priority:</strong>
                            <p>
                                <span class="badge bg-{{ $homeworkNotice->priority === 'high' ? 'danger' : ($homeworkNotice->priority === 'medium' ? 'warning' : 'success') }}">
                                    {{ ucfirst($homeworkNotice->priority) }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <strong>Type:</strong>
                            <p>{{ ucfirst($homeworkNotice->type) }}</p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <div class="border p-3 bg-light">
                            {!! nl2br(e($homeworkNotice->description)) !!}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Created At:</strong>
                        <p>{{ $homeworkNotice->created_at ? $homeworkNotice->created_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Updated At:</strong>
                        <p>{{ $homeworkNotice->updated_at ? $homeworkNotice->updated_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection