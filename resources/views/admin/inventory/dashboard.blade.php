@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Inventory Dashboard</h1>
            <p>Welcome to the Inventory Management System, {{ Auth::user()->name }}!</p>
        </div>
    </div>
    
    <!-- Inventory Overview Stats -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Assets</h5>
                    <p class="card-text display-4">{{ $totalAssets }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Active Assets</h5>
                    <p class="card-text display-4">{{ $activeAssets }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Categories</h5>
                    <p class="card-text display-4">{{ $totalCategories }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Low Stock</h5>
                    <p class="card-text display-4">{{ $lowStockAssets }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inventory Management Areas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Inventory Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Asset Master -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-box-seam text-primary"></i> Asset Master</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.assets.index') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">View Assets</a>
                                        <a href="{{ route('admin.assets.create') }}" class="btn btn-sm btn-outline-primary d-block">Add New Asset</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categories -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-tags text-success"></i> Categories</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.categories.index') }}" class="btn btn-sm btn-outline-success mb-2 d-block">View Categories</a>
                                        <a href="{{ route('admin.inventory.categories.create') }}" class="btn btn-sm btn-outline-success d-block">Add Category</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Furniture -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-chair text-warning"></i> Furniture</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.furniture') }}" class="btn btn-sm btn-outline-warning mb-2 d-block">Furniture Management</a>
                                        <a href="{{ route('admin.assets.create') }}?category=furniture" class="btn btn-sm btn-outline-warning d-block">Add Furniture</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lab Equipment -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-flask text-info"></i> Lab Equipment</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.lab-equipment') }}" class="btn btn-sm btn-outline-info mb-2 d-block">Lab Equipment</a>
                                        <a href="{{ route('admin.assets.create') }}?category=lab_equipment" class="btn btn-sm btn-outline-info d-block">Add Equipment</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <!-- Electronics -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-laptop text-danger"></i> Electronics</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.electronics') }}" class="btn btn-sm btn-outline-danger mb-2 d-block">Electronics</a>
                                        <a href="{{ route('admin.assets.create') }}?category=electronics" class="btn btn-sm btn-outline-danger d-block">Add Electronics</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-file-earmark-bar-graph text-primary"></i> Reports</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.reports') }}" class="btn btn-sm btn-outline-primary mb-2 d-block">View Reports</a>
                                        <a href="#" class="btn btn-sm btn-outline-primary d-block">Export Data</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Audit Logs -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-clipboard-check text-success"></i> Audit Logs</h6>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.inventory.audit-logs') }}" class="btn btn-sm btn-outline-success mb-2 d-block">View Logs</a>
                                        <a href="#" class="btn btn-sm btn-outline-success d-block">Export Logs</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Warranty Alerts -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><i class="bi bi-exclamation-triangle text-warning"></i> Warranty Alerts</h6>
                                    <div class="mt-3">
                                        <div class="alert alert-warning small">
                                            <strong>{{ $expiringWarranties->count() }}</strong> assets expiring soon
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Assets by Category</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($assetsByCategory as $category)
                            <div class="col-md-2 col-4 mb-2">
                                <div class="text-center">
                                    <div class="fw-bold">{{ $category->count }}</div>
                                    <small class="text-muted">{{ Str::limit($category->category_name, 15) }}</small>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <p class="text-muted text-center">No assets found by category.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
