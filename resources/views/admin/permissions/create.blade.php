@extends('layouts.admin')

@section('title', 'Create Permission')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create New Permission</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.permissions.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="name">Permission Name</label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Use lowercase letters, numbers, and hyphens. Example: manage-students</small>
                        </div>

                        <div class="form-group">
                            <label for="guard_name">Guard Name</label>
                            <input type="text" name="guard_name" id="guard_name" class="form-control @error('guard_name') is-invalid @enderror" 
                                   value="{{ old('guard_name', 'web') }}" required>
                            @error('guard_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Typically 'web' for web authentication</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Create Permission</button>
                            <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection