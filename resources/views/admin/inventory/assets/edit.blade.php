@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Edit Asset: {{ $asset->name }}</h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.inventory.assets.update', $asset) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Asset Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $asset->name) }}" required>
                                    @error('name')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category *</label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vendor" class="form-label">Vendor</label>
                                    <input type="text" class="form-control" id="vendor" name="vendor" value="{{ old('vendor', $asset->vendor) }}">
                                    @error('vendor')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cost" class="form-label">Cost (â‚¹)</label>
                                    <input type="number" class="form-control" id="cost" name="cost" value="{{ old('cost', $asset->cost) }}" step="0.01" min="0">
                                    @error('cost')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $asset->purchase_date) }}">
                                    @error('purchase_date')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="warranty_expiry_date" class="form-label">Warranty Expiry Date</label>
                                    <input type="date" class="form-control" id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date', $asset->warranty_expiry_date) }}">
                                    @error('warranty_expiry_date')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="condition" class="form-label">Condition *</label>
                                    <select class="form-control" id="condition" name="condition" required>
                                        <option value="new" {{ old('condition', $asset->condition) == 'new' ? 'selected' : '' }}>New</option>
                                        <option value="good" {{ old('condition', $asset->condition) == 'good' ? 'selected' : '' }}>Good</option>
                                        <option value="repair" {{ old('condition', $asset->condition) == 'repair' ? 'selected' : '' }}>Repair</option>
                                        <option value="scrap" {{ old('condition', $asset->condition) == 'scrap' ? 'selected' : '' }}>Scrap</option>
                                    </select>
                                    @error('condition')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active" {{ old('status', $asset->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="in_use" {{ old('status', $asset->status) == 'in_use' ? 'selected' : '' }}>In Use</option>
                                        <option value="under_repair" {{ old('status', $asset->status) == 'under_repair' ? 'selected' : '' }}>Under Repair</option>
                                        <option value="disposed" {{ old('status', $asset->status) == 'disposed' ? 'selected' : '' }}>Disposed</option>
                                    </select>
                                    @error('status')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" value="{{ old('location', $asset->location) }}" placeholder="e.g., Room 101, Physics Lab">
                                    @error('location')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}">
                                    @error('serial_number')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $asset->description) }}</textarea>
                            @error('description')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="{{ old('quantity', $asset->quantity) }}" min="1" required>
                            @error('quantity')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="warranty_details" class="form-label">Warranty Details</label>
                            <textarea class="form-control" id="warranty_details" name="warranty_details" rows="2">{{ old('warranty_details', $asset->warranty_details) }}</textarea>
                            @error('warranty_details')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Asset</button>
                            <a href="{{ route('admin.assets.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
