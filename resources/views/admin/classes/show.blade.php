@extends('layouts.admin')

@section('title', 'Class Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Class Details</h4>
                    <div class="card-tools">
                        <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Classes
                        </a>
                        <a href="{{ route('admin.classes.edit', $class->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Class Name:</label>
                                <p class="form-control-static">{{ $class->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Capacity:</label>
                                <p class="form-control-static">{{ $class->capacity ?: 'Unlimited' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description:</label>
                        <p class="form-control-static">{!! $class->description ? nl2br(e($class->description)) : 'N/A' !!}</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Created At:</label>
                                <p class="form-control-static">{{ $class->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Updated At:</label>
                                <p class="form-control-static">{{ $class->updated_at->format('d M Y, h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
