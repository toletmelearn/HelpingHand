@extends('layouts.admin')

@section('title', 'Inventory Reports')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Inventory Reports</h4>
                    <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Back to Inventory</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5>Total Assets</h5>
                                    <h3>{{ $totalAssets }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5>Active Assets</h5>
                                    <h3>{{ $activeAssets }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5>Assets Under Repair</h5>
                                    <h3>{{ $repairAssets }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <h5>Low Stock Items</h5>
                                    <h3>{{ $lowStockItems }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h5>Report Filters</h5>
                            <form method="GET" action="{{ route('inventory.reports') }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Date Range</label>
                                        <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label>&nbsp;</label>
                                        <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Category</label>
                                        <select class="form-control" name="category_id">
                                            <option value="">All Categories</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Generate Report</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h5>Available Reports</h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Asset Valuation Report</h5>
                                            <p>Detailed report of asset values</p>
                                            <a href="{{ route('inventory.reports.valuation') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Category Distribution</h5>
                                            <p>Asset distribution by category</p>
                                            <a href="{{ route('inventory.reports.category-distribution') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Damaged/Scrap Report</h5>
                                            <p>List of damaged or scrapped assets</p>
                                            <a href="{{ route('inventory.reports.damaged') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Room-wise Inventory</h5>
                                            <p>Assets distributed by location</p>
                                            <a href="{{ route('inventory.reports.location') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Maintenance Cost Summary</h5>
                                            <p>Summary of maintenance costs</p>
                                            <a href="{{ route('inventory.reports.maintenance') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Warranty Expiry Report</h5>
                                            <p>Assets with expiring warranties</p>
                                            <a href="{{ route('inventory.reports.warranty') }}" class="btn btn-primary">Generate</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5>Export Options</h5>
                            <div class="btn-group" role="group">
                                <a href="{{ route('inventory.reports.export', ['format' => 'pdf']) }}" 
                                   class="btn btn-danger">
                                    <i class="fas fa-file-pdf"></i> Export as PDF
                                </a>
                                <a href="{{ route('inventory.reports.export', ['format' => 'excel']) }}" 
                                   class="btn btn-success">
                                    <i class="fas fa-file-excel"></i> Export as Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection