@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Examination Pattern Details</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Examination Pattern: {{ $examinationPattern->name }}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $examinationPattern->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Code:</strong></td>
                                    <td>{{ $examinationPattern->code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $examinationPattern->is_active ? 'success' : 'secondary' }}">
                                            {{ $examinationPattern->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Default:</strong></td>
                                    <td>
                                        @if($examinationPattern->is_default)
                                            <span class="badge bg-info">Yes</span>
                                        @else
                                            <span class="badge bg-light text-dark">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Sort Order:</strong></td>
                                    <td>{{ $examinationPattern->sort_order }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $examinationPattern->description ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Pattern Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $examinationPattern->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ $examinationPattern->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                            
                            <h6 class="mt-3">Pattern Configuration</h6>
                            <div class="border p-3 bg-light">
                                <pre class="text-wrap"><code>{{ json_encode($examinationPattern->pattern_config, JSON_PRETTY_PRINT) }}</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.examination-patterns.index') }}" class="btn btn-secondary">Back to List</a>
                        <div>
                            <a href="{{ route('admin.examination-patterns.edit', $examinationPattern->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('admin.examination-patterns.destroy', $examinationPattern->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this examination pattern?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
