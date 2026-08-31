@extends('layouts.admin')

@section('title', 'Datesheets - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-0"><i class="fas fa-calendar-alt"></i> Datesheets</h2>
                    <p class="text-muted mb-0">Plan, review, and publish examination schedules.</p>
                </div>
                <a href="{{ route('admin.datesheets.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> New Datesheet
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card">
        <div class="card-header bg-primary text-white"><h4 class="mb-0"><i class="fas fa-list"></i> All Datesheets</h4></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th><th>Type</th><th>Session</th><th>Window</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($datesheets as $d)
                            <tr>
                                <td>{{ $d->name }}</td>
                                <td>{{ $d->exam_type }}</td>
                                <td>{{ $d->academicSession->name ?? 'N/A' }}</td>
                                <td>{{ $d->start_date->format('d M Y') }} - {{ $d->end_date->format('d M Y') }}</td>
                                <td>
                                    @php
                                        $badge = ['draft'=>'secondary','under_review'=>'warning','approved'=>'info','published'=>'success'][$d->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ str_replace('_',' ',ucfirst($d->status)) }}</span>
                                    @if($d->superseded_by_id)
                                        <span class="badge bg-dark">Superseded</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.datesheets.show', $d) }}" class="btn btn-sm btn-info">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No datesheets yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $datesheets->links() }}</div>
    </div>
</div>
@endsection
