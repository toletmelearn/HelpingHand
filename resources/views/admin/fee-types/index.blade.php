@extends('layouts.admin')

@section('title', 'Fee Head Master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Fee Head Master</h4>
                <div class="page-title-right d-flex align-items-center gap-3">
                    <a href="{{ route('admin.fee-types.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> New Fee Head
                    </a>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fee-structures.index') }}">Fee Structures</a></li>
                        <li class="breadcrumb-item active">Fee Head Master</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Optional</th>
                                    <th>Default Frequency</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($feeTypes as $type)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $type->name }}</span>
                                            <small class="d-block text-muted">{{ $type->description }}</small>
                                        </td>
                                        <td><span class="badge bg-secondary text-capitalize">{{ $type->category ?? 'recurring' }}</span></td>
                                        <td>{{ $type->is_optional ? 'Yes' : 'No' }}</td>
                                        <td class="text-capitalize">{{ $type->default_frequency ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $type->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($type->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="{{ route('admin.fee-types.edit', $type->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                @if($type->status === 'active')
                                                    <form method="POST" action="{{ route('admin.fee-types.deactivate', $type->id) }}">
                                                        @csrf @method('PUT')
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">Deactivate</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.fee-types.activate', $type->id) }}">
                                                        @csrf @method('PUT')
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No fee heads yet.</td></tr>
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
