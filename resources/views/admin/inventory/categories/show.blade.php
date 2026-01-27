@extends('layouts.admin')

@section('title', 'View Asset Category: ' . $category->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Asset Category Details: {{ $category->name }}</h4>
                    <div>
                        <a href="{{ route('inventory.categories.edit', $category->id) }}" class="btn btn-primary">Edit</a>
                        <a href="{{ route('inventory.categories.index') }}" class="btn btn-secondary">Back to Categories</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Name:</strong></label>
                                <p>{{ $category->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Type:</strong></label>
                                <p>
                                    @if($category->type == 'furniture')
                                        Furniture
                                    @elseif($category->type == 'lab_equipment')
                                        Lab Equipment
                                    @elseif($category->type == 'electronics')
                                        Electronics
                                    @elseif($category->type == 'sports')
                                        Sports
                                    @elseif($category->type == 'office')
                                        Office
                                    @else
                                        General
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Description:</strong></label>
                        <p>{{ $category->description ?? 'N/A' }}</p>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Status:</strong></label>
                        <p>
                            @if($category->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Created:</strong></label>
                        <p>{{ $category->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Last Updated:</strong></label>
                        <p>{{ $category->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection