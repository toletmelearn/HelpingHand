@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Examination Patterns</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Examination Patterns</h6>
                    <a href="{{ route('admin.examination-patterns.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New Examination Pattern
                    </a>
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
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Default</th>
                                    <th>Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($examinationPatterns as $examinationPattern)
                                    <tr>
                                        <td>{{ $examinationPattern->name }}</td>
                                        <td>{{ $examinationPattern->code }}</td>
                                        <td>
                                            <span class="badge bg-{{ $examinationPattern->is_active ? 'success' : 'secondary' }}">
                                                {{ $examinationPattern->is_active ? 'Active' : 'Inactive' }
                                            </span>
                                        </td>
                                        <td>
                                            @if($examinationPattern->is_default)
                                                <span class="badge bg-info">Default</span>
                                            @else
                                                <span class="badge bg-light text-dark">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $examinationPattern->sort_order }}</td>
                                        <td>
                                            <a href="{{ route('admin.examination-patterns.show', $examinationPattern->id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.examination-patterns.edit', $examinationPattern->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.examination-patterns.destroy', $examinationPattern->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this examination pattern?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No examination patterns found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
