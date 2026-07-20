@extends('layouts.admin')

@section('title', 'Backup & Disaster Recovery')

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
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Backups</li>
                    </ol>
                </nav>
                <h3 class="fw-bold text-dark"><i class="bi bi-cloud-arrow-up text-primary me-2"></i> Disaster Recovery Center</h3>
                <p class="text-muted">Generate instant database dumps or system upload archives, verify backup health, and perform single-click state restoration.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Manual Backup Form -->
        <div class="col-lg-4 col-12">
            <div class="card glass-card border-0 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i> Create Instant Backup</h5>
                <form action="{{ route('operations.backup.run') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Backup Type</label>
                        <select name="type" class="form-select">
                            <option value="database">Database Only (.sql)</option>
                            <option value="files">Uploaded Files Only (.zip)</option>
                            <option value="full">Full Backup (DB + Files)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Backup Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Describe the purpose of this backup point (e.g. Prior to Student Import)."></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="incrementalCheck" disabled>
                            <label class="form-check-input-label text-muted small" for="incrementalCheck">Incremental Files Backup</label>
                        </div>
                        <small class="text-muted d-block mt-1">Saves disk utilization by copying altered files only (Enterprise only).</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-download me-1"></i> Start Backup Job</button>
                </form>
            </div>

            <!-- Restore Wizard Card -->
            <div class="card glass-card border-0 p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-counterclockwise me-1 text-warning"></i> Quick Restore Wizard</h5>
                <p class="text-muted small">Select an archive from the history table and click "Restore". Restoring will replace current system states. Ensure database constraints are isolated first.</p>
                <div class="alert alert-warning small border-0 bg-warning-subtle text-warning-emphasis mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i> <strong>Warning:</strong> Restore operations cannot be undone!
                </div>
            </div>
        </div>

        <!-- Backup History -->
        <div class="col-lg-8 col-12">
            <div class="card glass-card border-0">
                <div class="card-header bg-light border-0 p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-1 text-primary"></i> Backup Execution History</h6>
                    <span class="badge bg-secondary">Total: {{ count($backups) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($backups as $item)
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block text-truncate" style="max-width: 200px;" title="{{ $item->filename }}">
                                                {{ $item->filename }}
                                            </strong>
                                            <span class="text-muted small">{{ $item->notes ?: 'No notes.' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ strtoupper($item->type) }}
                                            </span>
                                        </td>
                                        <td>{{ $item->size_formatted }}</td>
                                        <td>
                                            @if($item->status === 'completed')
                                                <span class="badge bg-success">SUCCESS</span>
                                            @elseif($item->status === 'running')
                                                <span class="badge bg-info">RUNNING</span>
                                            @else
                                                <span class="badge bg-danger">FAILED</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $item->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @if($item->status === 'completed')
                                                    <a href="{{ route('operations.backup.download', $item->id) }}" class="btn btn-sm btn-outline-primary" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <form action="{{ route('operations.backup.restore', $item->id) }}" method="POST" onsubmit="return confirm('WARNING: Are you absolutely sure you want to restore the ERP system to this backup point? All current unsaved changes will be overwritten!')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Restore">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('operations.backup.delete', $item->id) }}" method="POST" onsubmit="return confirm('Delete this backup file and entry permanently?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-cloud-slash fs-1 d-block mb-2"></i>
                                            No backup history logs found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
