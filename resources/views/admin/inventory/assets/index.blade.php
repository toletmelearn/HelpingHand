@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Asset Master</h1>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="{{ route('admin.inventory.assets.create') }}" class="btn btn-primary">Add New Asset</a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.assets.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>In Use</option>
                        <option value="under_repair" {{ request('status') == 'under_repair' ? 'selected' : '' }}>Under Repair</option>
                        <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>Disposed</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="condition" class="form-label">Condition</label>
                    <select name="condition" id="condition" class="form-select">
                        <option value="">All Conditions</option>
                        <option value="new" {{ request('condition') == 'new' ? 'selected' : '' }}>New</option>
                        <option value="good" {{ request('condition') == 'good' ? 'selected' : '' }}>Good</option>
                        <option value="repair" {{ request('condition') == 'repair' ? 'selected' : '' }}>Repair</option>
                        <option value="scrap" {{ request('condition') == 'scrap' ? 'selected' : '' }}>Scrap</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="Enter location" value="{{ request('location') }}">
                </div>
                
                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Name, Code, Vendor" value="{{ request('search') }}">
                </div>
                
                <div class="col-md-12 mt-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.assets.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assets Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Asset Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Condition</th>
                            <th>Location</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td>{{ $asset->asset_code }}</td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->category->name ?? 'N/A' }}</td>
                                <td>{{ $asset->vendor }}</td>
                                <td>{{ $asset->cost ? 'â‚¹' . number_format($asset->cost, 2) : 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-{{ $asset->status === 'active' ? 'success' : ($asset->status === 'in_use' ? 'primary' : ($asset->status === 'under_repair' ? 'warning' : 'danger')) }}">
                                        {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $asset->condition === 'new' ? 'success' : ($asset->condition === 'good' ? 'primary' : ($asset->condition === 'repair' ? 'warning' : 'danger')) }}">
                                        {{ ucfirst($asset->condition) }}
                                    </span>
                                </td>
                                <td>{{ $asset->location ?: 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-{{ $asset->available_quantity > 0 ? 'success' : 'danger' }}">
                                        {{ $asset->available_quantity }}/{{ $asset->quantity }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.inventory.assets.show', $asset) }}" class="btn btn-sm btn-outline-info">View</a>
                                    <a href="{{ route('admin.inventory.assets.edit', $asset) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">No assets found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center">
                {{ $assets->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
