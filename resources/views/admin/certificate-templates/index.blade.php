@extends('layouts.app')

@section('title', 'Certificate Templates')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Certificate Templates</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Certificate Templates</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Certificate Templates</h4>
                    <a href="{{ route('admin.certificate-templates.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Template
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Default</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $template)
                                <tr>
                                    <td>{{ $template->name }}</td>
                                    <td>
                                        <span class="badge bg-primary">{{ strtoupper($template->type) }}</span>
                                    </td>
                                    <td>
                                        @if($template->is_default)
                                            <span class="badge bg-success">Default</span>
                                        @else
                                            <span class="badge bg-secondary">Not Default</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($template->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $template->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $template->created_at->format('d/m/Y h:i A') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="{{ route('admin.certificate-templates.show', $template->id) }}"><i class="fas fa-eye text-info"></i> View</a></li>
                                                <li><a class="dropdown-item" href="{{ route('admin.certificate-templates.edit', $template->id) }}"><i class="fas fa-edit text-warning"></i> Edit</a></li>
                                                @if(!$template->is_default)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.certificate-templates.set-default', $template->id) }}" style="display: inline;">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-primary" onclick="return confirm('Are you sure you want to set this template as default?')">
                                                                <i class="fas fa-star"></i> Set as Default
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('admin.certificate-templates.destroy', $template->id) }}" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this template?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No certificate templates found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $templates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection