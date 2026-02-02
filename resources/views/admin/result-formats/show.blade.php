@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Result Format Details</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Result Format: {{ $resultFormat->name }}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $resultFormat->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Code:</strong></td>
                                    <td>{{ $resultFormat->code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $resultFormat->is_active ? 'success' : 'secondary' }}">
                                            {{ $resultFormat->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Default:</strong></td>
                                    <td>
                                        @if($resultFormat->is_default)
                                            <span class="badge bg-info">Yes</span>
                                        @else
                                            <span class="badge bg-light text-dark">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Sort Order:</strong></td>
                                    <td>{{ $resultFormat->sort_order }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $resultFormat->description ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Template Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $resultFormat->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ $resultFormat->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                            
                            <h6 class="mt-3">HTML Template</h6>
                            <div class="border p-3 bg-light">
                                <pre class="text-wrap"><code>{{ htmlspecialchars($resultFormat->template_html) }}</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.result-formats.index') }}" class="btn btn-secondary">Back to List</a>
                        <div>
                            <a href="{{ route('admin.result-formats.edit', $resultFormat->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('admin.result-formats.destroy', $resultFormat->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this result format?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
