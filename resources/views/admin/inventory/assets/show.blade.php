@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Asset Details: {{ $asset->name }}</h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Asset Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Asset Code:</strong> {{ $asset->asset_code }}</p>
                            <p><strong>Name:</strong> {{ $asset->name }}</p>
                            <p><strong>Category:</strong> {{ $asset->category->name ?? 'N/A' }}</p>
                            <p><strong>Vendor:</strong> {{ $asset->vendor ?: 'N/A' }}</p>
                            <p><strong>Cost:</strong> {{ $asset->cost ? '₹' . number_format($asset->cost, 2) : 'N/A' }}</p>
                            <p><strong>Purchase Date:</strong> {{ $asset->purchase_date ? $asset->purchase_date->format('d M Y') : 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Condition:</strong> 
                                <span class="badge bg-{{ $asset->condition === 'new' ? 'success' : ($asset->condition === 'good' ? 'primary' : ($asset->condition === 'repair' ? 'warning' : 'danger')) }}">
                                    {{ ucfirst($asset->condition) }}
                                </span>
                            </p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-{{ $asset->status === 'active' ? 'success' : ($asset->status === 'in_use' ? 'primary' : ($asset->status === 'under_repair' ? 'warning' : 'danger')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                </span>
                            </p>
                            <p><strong>Location:</strong> {{ $asset->location ?: 'N/A' }}</p>
                            <p><strong>Serial Number:</strong> {{ $asset->serial_number ?: 'N/A' }}</p>
                            <p><strong>Warranty Expiry:</strong> {{ $asset->warranty_expiry_date ? $asset->warranty_expiry_date->format('d M Y') : 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Total Quantity:</strong> {{ $asset->quantity }}</p>
                            <p><strong>Available Quantity:</strong> {{ $asset->available_quantity }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Warranty Details:</strong> {{ $asset->warranty_details ?: 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p>{{ $asset->description ?: 'N/A' }}</p>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.inventory.assets.edit', $asset) }}" class="btn btn-primary">Edit Asset</a>
                        <a href="{{ route('admin.assets.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.inventory.assets.edit', $asset) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit Asset
                        </a>
                        <a href="#" class="btn btn-outline-info">
                            <i class="bi bi-clock-history"></i> View History
                        </a>
                        <a href="#" class="btn btn-outline-warning">
                            <i class="bi bi-tools"></i> Maintenance
                        </a>
                        <a href="#" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i> Dispose Asset
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Recent Transactions</h5>
                </div>
                <div class="card-body">
                    @forelse($asset->transactions->take(5) as $transaction)
                        <div class="border-bottom pb-2 mb-2">
                            <p class="mb-1">
                                <small class="text-muted">{{ $transaction->transaction_type }}</small>
                            </p>
                            <p class="mb-1">
                                <small>{{ $transaction->created_at->format('d M Y') }}</small>
                            </p>
                            <p class="mb-0">
                                <small>By: {{ $transaction->user->name ?? 'Unknown' }}</small>
                            </p>
                        </div>
                    @empty
                        <p class="text-muted">No recent transactions.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection