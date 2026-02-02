@extends('layouts.admin')

@section('title', 'Document Format Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Document Format Details: {{ $documentFormat->name }}</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ID:</label>
                                <p class="form-control-static">{{ $documentFormat->id }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Name:</label>
                                <p class="form-control-static">{{ $documentFormat->name }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Type:</label>
                                <p class="form-control-static">{{ ucfirst($documentFormat->type ?? 'General') }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Description:</label>
                                <p class="form-control-static">{{ $documentFormat->description ?: 'N/A' }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Header Content:</label>
                                <div class="form-control-static">{{ $documentFormat->header_content ?: 'N/A' }}</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Footer Content:</label>
                                <div class="form-control-static">{{ $documentFormat->footer_content ?: 'N/A' }}</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status:</label>
                                <p class="form-control-static">
                                    @if($documentFormat->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label>Default:</label>
                                <p class="form-control-static">
                                    @if($documentFormat->is_default)
                                        <span class="badge badge-primary">Yes</span>
                                    @else
                                        <span class="badge badge-light">No</span>
                                    @endif
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label>Page Size:</label>
                                <p class="form-control-static">{{ $documentFormat->page_size }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Orientation:</label>
                                <p class="form-control-static">{{ ucfirst($documentFormat->orientation) }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Margins (Top, Bottom, Left, Right):</label>
                                <p class="form-control-static">{{ $documentFormat->margin_top }}mm, {{ $documentFormat->margin_bottom }}mm, {{ $documentFormat->margin_left }}mm, {{ $documentFormat->margin_right }}mm</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Created At:</label>
                                <p class="form-control-static">{{ $documentFormat->created_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Updated At:</label>
                                <p class="form-control-static">{{ $documentFormat->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <a href="{{ route('admin.document-formats.index') }}" class="btn btn-secondary">Back to Document Formats</a>
                        <a href="{{ route('admin.document-formats.edit', $documentFormat) }}" class="btn btn-warning">Edit Document Format</a>
                        @if(!$documentFormat->is_default)
                            <form action="{{ route('admin.document-formats.set-default', $documentFormat) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary" title="Set as Default">Set as Default</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection