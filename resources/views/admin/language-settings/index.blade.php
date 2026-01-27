@extends('layouts.app')

@section('title', 'Language Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Language Management</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Languages</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Supported Languages</h4>
                    <a href="{{ route('admin.language-settings.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Language
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Locale</th>
                                    <th>Name</th>
                                    <th>Default</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($languages as $language)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">{{ $language->locale }}</span>
                                    </td>
                                    <td>{{ $language->name }}</td>
                                    <td>
                                        @if($language->is_default)
                                            <span class="badge bg-success">Default</span>
                                        @else
                                            <span class="badge bg-secondary">Not Default</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($language->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $language->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $language->created_at->format('d/m/Y h:i A') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="{{ route('admin.language-settings.show', $language->id) }}"><i class="fas fa-eye text-info"></i> View</a></li>
                                                <li><a class="dropdown-item" href="{{ route('admin.language-settings.edit', $language->id) }}"><i class="fas fa-edit text-warning"></i> Edit</a></li>
                                                @if(!$language->is_default)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.language-settings.set-default', $language->id) }}" style="display: inline;">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-primary" onclick="return confirm('Are you sure you want to set this language as default?')">
                                                                <i class="fas fa-star"></i> Set as Default
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('admin.language-settings.toggle-status', $language->id) }}" style="display: inline;">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="dropdown-item text-{{ $language->is_active ? 'danger' : 'success' }}">
                                                            <i class="fas fa-{{ $language->is_active ? 'times' : 'check' }}"></i> 
                                                            {{ $language->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('admin.language-settings.destroy', $language->id) }}" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this language?')">
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
                                    <td colspan="7" class="text-center">No languages configured.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $languages->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection