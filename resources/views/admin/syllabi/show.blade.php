@extends('layouts.app')

@section('title', 'Syllabus Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Syllabus Details</h4>
                    <div>
                        <a href="{{ route('admin.syllabi.index') }}" class="btn btn-secondary">Back to List</a>
                        <a href="{{ route('admin.syllabi.edit', $syllabus) }}" class="btn btn-primary">Edit</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Title:</strong></td>
                                    <td>{{ $syllabus->title }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Subject:</strong></td>
                                    <td>{{ $syllabus->subject }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $syllabus->class_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $syllabus->section }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $syllabus->status == 'active' ? 'success' : ($syllabus->status == 'archived' ? 'secondary' : 'warning') }}">
                                            {{ ucfirst($syllabus->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Additional Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Start Date:</strong></td>
                                    <td>{{ $syllabus->start_date ? $syllabus->start_date->format('d-m-Y') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>End Date:</strong></td>
                                    <td>{{ $syllabus->end_date ? $syllabus->end_date->format('d-m-Y') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Duration:</strong></td>
                                    <td>{{ $syllabus->total_duration_hours ? $syllabus->total_duration_hours . ' hours' : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Academic Session:</strong></td>
                                    <td>{{ $syllabus->academic_session ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td>{{ $syllabus->createdBy->name ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Description</h5>
                            <p>{!! nl2br(e($syllabus->description ?: 'No description provided.')) !!}</p>
                        </div>
                    </div>
                    
                    @if($syllabus->hasChapters())
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Chapters ({{ $syllabus->getChapterCount() }})</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Chapter</th>
                                            <th>Description</th>
                                            <th>Duration (Hours)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($syllabus->chapters as $chapter)
                                        <tr>
                                            <td><strong>{{ $chapter['title'] ?? 'N/A' }}</strong></td>
                                            <td>{{ $chapter['description'] ?? 'N/A' }}</td>
                                            <td>{{ $chapter['duration_hours'] ?? 'N/A' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($syllabus->learning_objectives)
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Learning Objectives</h5>
                            <ul class="list-group">
                                @foreach(json_decode($syllabus->learning_objectives, true) as $objective)
                                    <li class="list-group-item">{{ $objective }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                    
                    @if($syllabus->assessment_criteria)
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Assessment Criteria</h5>
                            <ul class="list-group">
                                @foreach(json_decode($syllabus->assessment_criteria, true) as $criteria)
                                    <li class="list-group-item">{{ $criteria }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Syllabus Coverage Progress -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Coverage Progress</h5>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo isset($coveragePercentage) ? $coveragePercentage : 0; ?>%;" 
                                     aria-valuenow="<?php echo isset($coveragePercentage) ? $coveragePercentage : 0; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round(isset($coveragePercentage) ? $coveragePercentage : 0, 2); ?>% Covered
                                </div>
                            </div>
                            <small class="text-muted">
                                {{ $syllabus->getChapterCount() }} chapters total | 
                                Estimated completion: {{ round($syllabus->getDurationPercentage(), 2) }}%
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <form action="{{ route('admin.syllabi.destroy', $syllabus) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this syllabus?')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection