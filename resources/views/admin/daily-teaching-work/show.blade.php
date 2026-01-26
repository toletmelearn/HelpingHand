@extends('layouts.app')

@section('title', 'Daily Teaching Work Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Daily Teaching Work Details</h4>
                    <div>
                        <a href="{{ route('admin.daily-teaching-work.index') }}" class="btn btn-secondary">Back to List</a>
                        <a href="{{ route('admin.daily-teaching-work.edit', $dailyTeachingWork) }}" class="btn btn-primary">Edit</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Date:</strong></td>
                                    <td>{{ $dailyTeachingWork->date->format('d-m-Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $dailyTeachingWork->class_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $dailyTeachingWork->section }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Subject:</strong></td>
                                    <td>{{ $dailyTeachingWork->subject }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Period:</strong></td>
                                    <td>{{ $dailyTeachingWork->period_number ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Teacher:</strong></td>
                                    <td>{{ $dailyTeachingWork->teacher->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $dailyTeachingWork->status == 'published' ? 'success' : ($dailyTeachingWork->status == 'draft' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($dailyTeachingWork->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Additional Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Topic Covered:</strong></td>
                                    <td>{{ $dailyTeachingWork->topic_covered }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Academic Session:</strong></td>
                                    <td>{{ $dailyTeachingWork->academic_session ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td>{{ $dailyTeachingWork->createdBy->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created At:</strong></td>
                                    <td>{{ $dailyTeachingWork->created_at->format('d-m-Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated By:</strong></td>
                                    <td>{{ $dailyTeachingWork->updatedBy->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated At:</strong></td>
                                    <td>{{ $dailyTeachingWork->updated_at ? $dailyTeachingWork->updated_at->format('d-m-Y h:i A') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Teaching Summary</h5>
                            <p>{!! nl2br(e($dailyTeachingWork->teaching_summary ?: 'No teaching summary provided.')) !!}</p>
                        </div>
                    </div>
                    
                    @if($dailyTeachingWork->hasHomework())
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Homework Assignment</h5>
                            <p><strong>Description:</strong> {!! nl2br(e($dailyTeachingWork->homework['description'] ?? 'No homework assigned.')) !!}</p>
                            @if(isset($dailyTeachingWork->homework['due_date']))
                                <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($dailyTeachingWork->homework['due_date'])->format('d-m-Y') }}</p>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    @if($dailyTeachingWork->hasAttachments())
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Attachments ({{ $dailyTeachingWork->getAttachmentCount() }})</h5>
                            <div class="row">
                                @foreach($dailyTeachingWork->attachments as $index => $attachment)
                                <div class="col-md-3 mb-2">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            @if(in_array($attachment['extension'], ['pdf']))
                                                <i class="fas fa-file-pdf text-danger" style="font-size: 2em;"></i>
                                            @elseif(in_array($attachment['extension'], ['doc', 'docx']))
                                                <i class="fas fa-file-word text-primary" style="font-size: 2em;"></i>
                                            @elseif(in_array($attachment['extension'], ['jpg', 'jpeg', 'png']))
                                                <i class="fas fa-file-image text-info" style="font-size: 2em;"></i>
                                            @elseif(in_array($attachment['extension'], ['mp4', 'avi', 'mov']))
                                                <i class="fas fa-file-video text-warning" style="font-size: 2em;"></i>
                                            @else
                                                <i class="fas fa-file text-secondary" style="font-size: 2em;"></i>
                                            @endif
                                            <div class="mt-2">
                                                <small class="text-muted">{{ $attachment['name'] }}</small><br>
                                                <small class="text-muted">{{ round($attachment['size'] / 1024, 2) }} KB</small>
                                            </div>
                                            <div class="mt-2">
                                                <a href="{{ route('admin.daily-teaching-work.download-attachment', [$dailyTeachingWork, $index]) }}" 
                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <form action="{{ route('admin.daily-teaching-work.destroy', $dailyTeachingWork) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this daily teaching work?')">
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