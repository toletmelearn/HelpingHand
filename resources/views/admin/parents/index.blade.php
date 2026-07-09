@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h1 class="h3 mb-2 text-gray-800">Parent Directory</h1>
                <p class="mb-0">Search, monitor, and manage school parent profiles and credentials.</p>
            </div>
            @if(Route::has('imports.wizard'))
                <a href="{{ route('imports.wizard', ['module' => 'parents']) }}" class="btn btn-outline-secondary" title="Add many parent records at once from a spreadsheet">
                    <i class="fas fa-file-upload"></i> Bulk Import Parents
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4">{{ session('success') }}</div>
    @endif

    <!-- Filter/Search Bar -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.parents.index') }}" method="GET" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, or child's name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">-- All Statuses --</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter Directory</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Parent List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Registered Parent Accounts</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Parent Name</th>
                            <th>Contact Phone</th>
                            <th>Email Address</th>
                            <th>Linked Children</th>
                            <th>Account Status</th>
                            <th class="text-end px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parents as $parent)
                            <tr>
                                <td class="fw-bold">{{ $parent->name }}</td>
                                <td>{{ $parent->mobile ?: $parent->phone ?: 'N/A' }}</td>
                                <td>{{ $parent->email }}</td>
                                <td>
                                    @forelse($parent->students as $child)
                                        <a href="{{ route('admin.students.show', $child->id) }}" class="badge bg-info text-decoration-none me-1 p-2">
                                            <i class="fas fa-child me-1"></i> {{ $child->name }}
                                        </a>
                                    @empty
                                        <span class="text-muted" style="font-size: 0.85rem;">No linked students</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($parent->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end px-4">
                                    <a href="{{ route('admin.parents.show', $parent->id) }}" class="btn btn-primary btn-sm">
                                        Manage Profile <i class="fas fa-edit ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No parent records match your query.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($parents->hasPages())
            <div class="card-footer py-3">
                {{ $parents->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
