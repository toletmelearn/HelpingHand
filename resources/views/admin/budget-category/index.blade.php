@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Budget Categories</li>
                    </ol>
                </div>
                <h4 class="page-title">Budget Categories</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Budget Categories
                        <a href="{{ route('admin.budget-categories.create') }}" class="btn btn-primary btn-sm float-end">Add New Category</a>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <form method="GET" action="{{ route('admin.budget-categories.index') }}">
                            <div class="col-md-12">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <select name="type" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Types</option>
                                            @foreach($types as $key => $value)
                                                <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="is_active" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Statuses</option>
                                            @foreach($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ request('is_active') == $key ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" placeholder="Search categories..." value="{{ request('search') }}">
                                            <button class="btn btn-outline-secondary" type="submit">Search</button>
                                            <a href="{{ route('admin.budget-categories.index') }}" class="btn btn-outline-secondary">Clear</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Categories Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Default Allocation %</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td>{{ $category->name }}</td>
                                    <td>{{ $category->description ?: 'N/A' }}</td>
                                    <td>{{ $types[$category->type] ?? ucfirst($category->type) }}</td>
                                    <td>{{ $category->default_allocation_percentage }}%</td>
                                    <td>
                                        <span class="badge 
                                            @if($category->is_active) bg-success
                                            @else bg-secondary
                                            @endif">
                                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $category->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $category->created_at->format('d-m-Y H:i:s') }}</td>
                                    <td>
                                        <a href="{{ route('admin.budget-categories.show', $category) }}" class="btn btn-info btn-sm">View</a>
                                        <a href="{{ route('admin.budget-categories.edit', $category) }}" class="btn btn-primary btn-sm">Edit</a>
                                        <form action="{{ route('admin.budget-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this budget category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No budget categories found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $categories->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection