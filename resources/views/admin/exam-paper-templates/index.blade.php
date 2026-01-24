@extends('layouts.app')

@section('title', 'Exam Paper Templates')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Exam Paper Templates</h4>
                    <a href="{{ route('admin.exam-paper-templates.create') }}" class="btn btn-primary">Add New Template</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Academic Year</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $template)
                                <tr>
                                    <td>{{ $template->name }}</td>
                                    <td>{{ $template->subject }}</td>
                                    <td>{{ $template->class_section }}</td>
                                    <td>{{ $template->academic_year ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $template->is_active ? 'success' : 'secondary' }}">
                                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $template->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $template->created_at->format('d-m-Y') }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.exam-paper-templates.show', $template) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.exam-paper-templates.edit', $template) }}" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="{{ route('admin.exam-paper-templates.preview', $template) }}" class="btn btn-sm btn-secondary">Preview</a>
                                            <a href="{{ route('admin.exam-paper-templates.toggle-status', $template) }}" class="btn btn-sm {{ $template->is_active ? 'btn-warning' : 'btn-success' }}">
                                                {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                                            </a>
                                            <form action="{{ route('admin.exam-paper-templates.destroy', $template) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this template?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No exam paper templates found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        {{ $templates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection