@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Asset Categories</h1>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="{{ route('admin.inventory.categories.create') }}" class="btn btn-primary">Add New Category</a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.inventory.categories.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="furniture" {{ request('type') == 'furniture' ? 'selected' : '' }}>Furniture</option>
                        <option value="lab_equipment" {{ request('type') == 'lab_equipment' ? 'selected' : '' }}>Lab Equipment</option>
                        <option value="electronics" {{ request('type') == 'electronics' ? 'selected' : '' }}>Electronics</option>
                        <option value="sports" {{ request('type') == 'sports' ? 'selected' : '' }}>Sports</option>
                        <option value="office" {{ request('type') == 'office' ? 'selected' : '' }}>Office</option>
                        <option value="general" {{ request('type') == 'general' ? 'selected' : '' }}>General</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="is_active" class="form-label">Status</label>
                    <select name="is_active" id="is_active" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Category name" value="{{ request('search') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('admin.inventory.categories.index') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Categories Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Asset Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $category->type)) }}</td>
                                <td>{{ Str::limit($category->description, 50) }}</td>
                                <td>
                                    <span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $category->assets()->count() }}</td>
                                <td>
                                    <a href="{{ route('admin.inventory.categories.show', $category) }}" class="btn btn-sm btn-outline-info">View</a>
                                    <a href="{{ route('admin.inventory.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No categories found.</td>
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
@endsection