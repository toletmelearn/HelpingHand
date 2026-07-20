@extends('layouts.admin')

@section('title', 'Homework Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-tasks"></i> Homework Details
                    </h4>
                    <div>
                        <a href="{{ route('admin.professional-homework.index') }}" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-school"></i> Class:
                                </label>
                                <p>{{ $homework->schoolClass->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-book"></i> Subject:
                                </label>
                                <p>{{ $homework->subject->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-chalkboard-teacher"></i> Teacher:
                                </label>
                                <p>{{ $homework->teacherLogin->username ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-tag"></i> Type:
                                </label>
                                <p>
                                    <span class="badge badge-{{ $homework->type == 'homework' ? 'primary' : ($homework->type == 'notice' ? 'warning' : 'info') }}">
                                        {{ ucfirst($homework->type) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-exclamation-circle"></i> Priority:
                                </label>
                                <p>
                                    <span class="badge badge-{{ $homework->priority == 'high' ? 'danger' : ($homework->priority == 'medium' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($homework->priority) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-user-friends"></i> Parent Visibility:
                                </label>
                                <p>
                                    @if($homework->visible_to_parent)
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> Visible to Parents
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times"></i> Not Visible to Parents
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-calendar"></i> Due Date:
                                </label>
                                <p>{{ $homework->due_date ? $homework->due_date->format('F d, Y') : 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-calendar-plus"></i> Published Date:
                                </label>
                                <p>{{ $homework->publish_date->format('F d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-cog"></i> Status:
                                </label>
                                <p>
                                    <span class="badge badge-{{ $homework->status == 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($homework->status) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        @if($homework->section)
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-users"></i> Section:
                                </label>
                                <p>{{ $homework->section->name }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-heading"></i> Title
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {{ $homework->title }}
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-primary">
                            <i class="fas fa-file-alt"></i> Description
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($homework->description)) !!}
                        </div>
                    </div>
                    
                    @if($homework->parent_notes)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-info">
                            <i class="fas fa-user-friends"></i> Notes for Parents
                        </h5>
                        <div class="p-3 bg-info text-white rounded">
                            {!! nl2br(e($homework->parent_notes)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($homework->attachment_path)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-paperclip"></i> Attachment
                        </h5>
                        <div class="p-3 bg-light rounded">
                            <a href="{{ Storage::url($homework->attachment_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-download"></i> Download Attachment
                            </a>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-info">
                            <i class="fas fa-info-circle"></i> System Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="font-weight-bold">Created At:</label>
                                    <p>{{ $homework->created_at->format('F d, Y h:i A') }}</p>
                                </div>
                                <div class="info-group">
                                    <label class="font-weight-bold">Updated At:</label>
                                    <p>{{ $homework->updated_at->format('F d, Y h:i A') }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="font-weight-bold">ID:</label>
                                    <p>{{ $homework->id }}</p>
                                </div>
                                @if($homework->deleted_at)
                                <div class="info-group">
                                    <label class="font-weight-bold">Deleted At:</label>
                                    <p>{{ $homework->deleted_at->format('F d, Y h:i A') }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="{{ route('admin.professional-homework.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        
                        <form action="{{ route('admin.professional-homework.destroy', $homework) }}" 
                              method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this homework? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Homework
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection