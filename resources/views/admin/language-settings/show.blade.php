@extends('layouts.app')

@section('title', 'Language Details - ' . $languageSetting->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Language Details</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.language-settings.index') }}">Languages</a></li>
                        <li class="breadcrumb-item active">{{ $languageSetting->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Language: {{ $languageSetting->name }}</h4>
                    <div>
                        <a href="{{ route('admin.language-settings.edit', $languageSetting->id) }}" class="btn btn-warning btn-sm me-2">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="{{ route('admin.language-settings.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i> All Languages
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Locale Code:</th>
                                    <td><span class="badge bg-primary">{{ $languageSetting->locale }}</span></td>
                                </tr>
                                <tr>
                                    <th>Language Name:</th>
                                    <td>{{ $languageSetting->name }}</td>
                                </tr>
                                <tr>
                                    <th>Flag Code:</th>
                                    <td>{{ $languageSetting->flag ?: 'Not set' }}</td>
                                </tr>
                                <tr>
                                    <th>Is Default:</th>
                                    <td>
                                        @if($languageSetting->is_default)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Is Active:</th>
                                    <td>
                                        @if($languageSetting->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $languageSetting->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Updated By:</th>
                                    <td>{{ $languageSetting->updater->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $languageSetting->created_at->format('d/m/Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At:</th>
                                    <td>{{ $languageSetting->updated_at ? $languageSetting->updated_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.language-settings.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Languages
                        </a>
                        <div>
                            @if(!$languageSetting->is_default)
                                <form method="POST" action="{{ route('admin.language-settings.set-default', $languageSetting->id) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary me-2" onclick="return confirm('Are you sure you want to set this language as default?')">
                                        <i class="fas fa-star"></i> Set as Default
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.language-settings.toggle-status', $languageSetting->id) }}" style="display: inline;">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-{{ $languageSetting->is_active ? 'warning' : 'success' }} me-2">
                                    <i class="fas fa-{{ $languageSetting->is_active ? 'times' : 'check' }}"></i> 
                                    {{ $languageSetting->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.language-settings.destroy', $languageSetting->id) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this language?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection