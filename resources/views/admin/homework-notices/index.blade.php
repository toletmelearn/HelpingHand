@extends('layouts.admin')

@section('title', 'Homework & Notices')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Homework & Notices</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Homework & Notices</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Homework & Notices Management</h5>
                    <a href="{{ route('admin.homework-notices.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add New
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Assigned By</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($homeworkNotices as $notice)
                                <tr>
                                    <td>{{ $notice->title }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($notice->type == 'homework') bg-primary 
                                            @elseif($notice->type == 'notice') bg-info 
                                            @else bg-warning @endif">
                                            {{ ucfirst($notice->type) }}
                                        </span>
                                    </td>
                                    <td>{{ $notice->schoolClass->name ?? 'N/A' }}</td>
                                    <td>{{ $notice->subject->name ?? 'N/A' }}</td>
                                    <td>{{ $notice->assignedBy->name ?? 'N/A' }}</td>
                                    <td>{{ $notice->due_date ? $notice->due_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($notice->status == 'active') bg-success 
                                            @elseif($notice->status == 'inactive') bg-secondary 
                                            @elseif($notice->status == 'published') bg-info 
                                            @else bg-dark @endif">
                                            {{ ucfirst($notice->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($notice->priority == 'high') bg-danger 
                                            @elseif($notice->priority == 'medium') bg-warning 
                                            @else bg-success @endif">
                                            {{ ucfirst($notice->priority) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.homework-notices.show', $notice->id) }}" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.homework-notices.edit', $notice->id) }}" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('admin.homework-notices.destroy', $notice->id) }}" 
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this notice?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No homework or notices found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $homeworkNotices->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection