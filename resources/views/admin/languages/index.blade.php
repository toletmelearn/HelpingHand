@extends('layouts.admin')

@section('title', 'Language Management')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-translate"></i> Language Management
        </h1>
        <a href="{{ route('admin.languages.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Language
        </a>
    </div>

    <!-- Default Language Info -->
    @php
        $defaultLanguage = \App\Models\Language::default()->first();
    @endphp
    @if($defaultLanguage)
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            Default language is <strong>{{ $defaultLanguage->name }} ({{ $defaultLanguage->code }})</strong>
        </div>
    @endif

    <!-- Languages Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-globe"></i> Available Languages</h5>
        </div>
        <div class="card-body">
            @if($languages->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Language</th>
                                <th>Code</th>
                                <th>Flag</th>
                                <th>Status</th>
                                <th>Default</th>
                                <th>Translations</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($languages as $language)
                                <tr>
                                    <td>
                                        <strong>{{ $language->name }}</strong>
                                    </td>
                                    <td>
                                        <code>{{ $language->code }}</code>
                                    </td>
                                    <td>
                                        <span class="fs-3">{{ $language->flag_emoji }}</span>
                                    </td>
                                    <td>
                                        @if($language->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($language->is_default)
                                            <span class="badge bg-primary">Default</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $language->translations()->count() }}</span>
                                    </td>
                                    <td>
                                        {{ $language->sort_order }}
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('admin.languages.show', $language) }}" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.languages.translations', $language) }}" 
                                               class="btn btn-outline-success" title="Manage Translations">
                                                <i class="bi bi-translate"></i>
                                            </a>
                                            <a href="{{ route('admin.languages.edit', $language) }}" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @if(!$language->is_default && $language->translations()->count() == 0)
                                                <form action="{{ route('admin.languages.destroy', $language) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('Are you sure you want to delete this language?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    {{ $languages->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-globe fs-1 text-muted"></i>
                    <h4 class="mt-3">No Languages Found</h4>
                    <p class="text-muted">Add your first language to get started with multi-language support.</p>
                    <a href="{{ route('admin.languages.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Language
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Language Switcher Preview -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-toggle2-on"></i> Language Switcher Preview</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">This preview shows how the language switcher will appear in the navbar.</p>
            <div class="d-flex align-items-center">
                <span class="me-3">Current Language:</span>
                @foreach(\App\Models\Language::active()->orderBy('sort_order')->get() as $lang)
                    <a href="{{ route('admin.languages.switch', $lang->code) }}" 
                       class="btn btn-sm {{ app()->getLocale() == $lang->code ? 'btn-primary' : 'btn-outline-secondary' }} me-2"
                       title="Switch to {{ $lang->name }}">
                        {{ $lang->flag_emoji }} {{ strtoupper($lang->code) }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
