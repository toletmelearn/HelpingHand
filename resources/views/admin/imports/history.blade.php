@extends('layouts.admin')

@section('title', 'Import History Logs')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('imports.dashboard') }}">Data Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">History</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i> Import Sessions History</h3>
            <p class="text-muted">Review, verify, or rollback previously committed data uploads.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>UUID</th>
                            <th>Date Started</th>
                            <th>Module</th>
                            <th>Status</th>
                            <th>Success / Total</th>
                            <th>Errors</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $activity)
                            <tr>
                                <td><code style="font-size: 0.8rem;">{{ $activity->uuid }}</code></td>
                                <td>{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="badge bg-secondary text-capitalize">{{ $activity->module }}</span></td>
                                <td>
                                    @if($activity->status === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($activity->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @elseif($activity->status === 'rolled_back')
                                        <span class="badge bg-warning text-dark">Rolled Back</span>
                                    @else
                                        <span class="badge bg-info text-white">{{ ucfirst($activity->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $activity->success_rows }} / {{ $activity->total_rows }} rows</td>
                                <td>
                                    @if($activity->error_rows > 0)
                                        <a href="{{ route('imports.wizard.errors.download', ['module' => $activity->module, 'uuid' => $activity->uuid]) }}" class="text-danger fw-semibold text-decoration-none">
                                            <i class="bi bi-cloud-arrow-down-fill me-1"></i>{{ $activity->error_rows }} errors
                                        </a>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($activity->status === 'completed')
                                        <button class="btn btn-sm btn-outline-danger" onclick="triggerRollback('{{ $activity->uuid }}', '{{ $activity->module }}')"><i class="bi bi-arrow-counterclockwise"></i> Rollback</button>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No historical import sessions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($history->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $history->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function triggerRollback(uuid, module) {
    if (confirm('Are you sure you want to rollback this session? This will undo all changes and delete all newly created profiles.')) {
        fetch(`/admin/imports/wizard/${module}/rollback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ session_uuid: uuid })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Session rolled back successfully.');
                window.location.reload();
            } else {
                alert('Rollback failed: ' + data.message);
            }
        });
    }
}
</script>
@endsection
