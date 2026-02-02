@extends('layouts.admin')

@section('title', 'Document Formats')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Document Formats</h3>
                    <a href="{{ route('admin.document-formats.create') }}" class="btn btn-primary">Add New Format</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Default</th>
                                    <th>Page Size</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documentFormats as $documentFormat)
                                    <tr>
                                        <td>{{ $documentFormat->id }}</td>
                                        <td>{{ $documentFormat->name }}</td>
                                        <td>{{ ucfirst($documentFormat->type ?? 'General') }}</td>
                                        <td>
                                            @if($documentFormat->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($documentFormat->is_default)
                                                <span class="badge badge-primary">Default</span>
                                            @else
                                                <span class="badge badge-light">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $documentFormat->page_size }} ({{ $documentFormat->orientation }})</td>
                                        <td>{{ $documentFormat->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.document-formats.show', $documentFormat) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.document-formats.edit', $documentFormat) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.document-formats.destroy', $documentFormat) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document format?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                            @if(!$documentFormat->is_default)
                                                <form action="{{ route('admin.document-formats.set-default', $documentFormat) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Set as Default">Default</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No document formats found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $documentFormats->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection