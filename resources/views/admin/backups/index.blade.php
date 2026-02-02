@extends('layouts.admin')

@section('title', 'Backup Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Backup Management</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Backups</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Backup Records</h4>
                    <div>
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                            <i class="fas fa-plus"></i> Create Backup
                        </button>
                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#scheduleBackupModal">
                            <i class="fas fa-clock"></i> Schedule Backup
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($backups as $backup)
                                <tr>
                                    <td>{{ $backup->filename }}</td>
                                    <td>
                                        <span class="badge bg-primary">{{ $backup->type_label }}</span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($backup->location == 'local') bg-success
                                            @else bg-info
                                            @endif
                                        ">
                                            {{ $backup->location_label }}
                                        </span>
                                    </td>
                                    <td>{{ $backup->size_formatted }}</td>
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
                                    <td>{{ $backup->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $backup->created_at->format('d/m/Y h:i A') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                @if($backup->isCompleted())
                                                    <li><a class="dropdown-item" href="{{ route('admin.backups.download', $backup->id) }}"><i class="fas fa-download text-primary"></i> Download</a></li>
                                                @endif
                                                <li><a class="dropdown-item" href="{{ route('admin.backups.show', $backup->id) }}"><i class="fas fa-eye text-info"></i> View Details</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('admin.backups.destroy', $backup->id) }}" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this backup?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No backup records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $backups->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type" class="form-label">Backup Type</label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="full">Full Backup</option>
                            <option value="database">Database Only</option>
                            <option value="files">Files Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Storage Location</label>
                        <select name="location" id="location" class="form-control" required>
                            <option value="local">Local</option>
                            <option value="cloud">Cloud</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Enter any notes about this backup..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Backup Modal -->
<div class="modal fade" id="scheduleBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.backups.schedule') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="schedule_type" class="form-label">Backup Type</label>
                        <select name="type" id="schedule_type" class="form-control" required>
                            <option value="full">Full Backup</option>
                            <option value="database">Database Only</option>
                            <option value="files">Files Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="schedule_location" class="form-label">Storage Location</label>
                        <select name="location" id="schedule_location" class="form-control" required>
                            <option value="local">Local</option>
                            <option value="cloud">Cloud</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="schedule_date" class="form-label">Schedule Date & Time</label>
                        <input type="datetime-local" name="schedule_date" id="schedule_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="schedule_notes" class="form-label">Notes</label>
                        <textarea name="notes" id="schedule_notes" class="form-control" rows="3" placeholder="Enter any notes about this backup..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
