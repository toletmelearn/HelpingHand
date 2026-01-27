@extends('layouts.app')

@section('title', 'View Certificate Template - ' . $certificateTemplate->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Certificate Template Details</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.certificate-templates.index') }}">Certificate Templates</a></li>
                        <li class="breadcrumb-item active">{{ $certificateTemplate->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Template: {{ $certificateTemplate->name }}</h4>
                    <div>
                        <a href="{{ route('admin.certificate-templates.edit', $certificateTemplate->id) }}" class="btn btn-warning btn-sm me-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="{{ route('admin.certificate-templates.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i> All Templates
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Template Name:</th>
                                    <td>{{ $certificateTemplate->name }}</td>
                                </tr>
                                <tr>
                                    <th>Template Type:</th>
                                    <td><span class="badge bg-primary">{{ strtoupper($certificateTemplate->type) }}</span></td>
                                </tr>
                                <tr>
                                    <th>Default Template:</th>
                                    <td>
                                        @if($certificateTemplate->is_default)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Active Status:</th>
                                    <td>
                                        @if($certificateTemplate->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $certificateTemplate->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Updated By:</th>
                                    <td>{{ $certificateTemplate->updater->name ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $certificateTemplate->created_at->format('d/m/Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At:</th>
                                    <td>{{ $certificateTemplate->updated_at ? $certificateTemplate->updated_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mt-4">Template Variables</h5>
                    <div class="border p-3 rounded bg-light">
                        <ul class="list-unstyled">
                            @foreach($certificateTemplate->template_variables as $variable)
                                <li><code>{{ '{' . $variable . '}' }}</code></li>
                            @endforeach
                        </ul>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mt-4">Template Content</h5>
                    <div class="border p-3 rounded bg-light">
                        <pre class="text-wrap">{{ $certificateTemplate->template_content }}</pre>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.certificate-templates.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Templates
                        </a>
                        <div>
                            @if(!$certificateTemplate->is_default)
                                <form method="POST" action="{{ route('admin.certificate-templates.set-default', $certificateTemplate->id) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary me-1" onclick="return confirm('Are you sure you want to set this template as default?')">
                                        <i class="fas fa-star"></i> Set as Default
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('admin.certificate-templates.edit', $certificateTemplate->id) }}" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Template
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection