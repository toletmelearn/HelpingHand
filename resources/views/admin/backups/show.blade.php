@extends('layouts.admin')

@section('title', 'Backup Details - ' . $backup->filename)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Backup Details</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.backups.index') }}">Backups</a></li>
                        <li class="breadcrumb-item active">{{ $backup->filename }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Backup: {{ $backup->filename }}</h4>
                    <div>
                        @if($backup->isCompleted())
                            <a href="{{ route('admin.backups.download', $backup->id) }}" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-download"></i> Download
                            </a>
                        @endif
                        <a href="{{ route('admin.backups.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i> All Backups
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Filename:</th>
                                    <td>{{ $backup->filename }}</td>
                                </tr>
                                <tr>
                                    <th>Path:</th>
                                    <td>{{ $backup->path }}</td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td><span class="badge bg-primary">{{ $backup->type_label }}</span></td>
                                </tr>
                                <tr>
                                    <th>Location:</th>
                                    <td><span class="badge bg-success">{{ $backup->location_label }}</span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge 
                                            @if($backup->status == 'completed') bg-success
                                            @elseif($backup->status == 'pending') bg-warning
                                            @elseif($backup->status == 'failed') bg-danger
                                            @endif
                                        ">
                                            {{ $backup->status_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Size:</th>
                                    <td>{{ $backup->size_formatted }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $backup->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $backup->created_at->format('d/m/Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Scheduled At:</th>
                                    <td>{{ $backup->scheduled_at ? $backup->scheduled_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Completed At:</th>
                                    <td>{{ $backup->completed_at ? $backup->completed_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    @if($backup->notes)
                    <hr>
                    <h5>Notes</h5>
                    <div class="border p-3 rounded bg-light">
                        {{ $backup->notes }}
                    </div>
                    @endif
                    
                    @if($backup->metadata)
                    <hr>
                    <h5>Metadata</h5>
                    <div class="border p-3 rounded bg-light">
                        <pre>{{ json_encode($backup->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.backups.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Backups
                        </a>
                        <form method="POST" action="{{ route('admin.backups.destroy', $backup->id) }}" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this backup?')">
                                <i class="fas fa-trash"></i> Delete Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
