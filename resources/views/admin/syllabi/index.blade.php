@extends('layouts.app')

@section('title', 'Syllabus Management')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Syllabus</h4>
                    <a href="{{ route('admin.syllabi.create') }}" class="btn btn-primary">Add New Syllabus</a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <select name="subject" class="form-control">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="class_name" class="form-control">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class }}" {{ request('class_name') == $class ? 'selected' : '' }}>
                                            {{ $class }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="section" class="form-control">
                                    <option value="">All Sections</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section }}" {{ request('section') == $section ? 'selected' : '' }}>
                                            {{ $section }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                </select>
                            </div>
                            <div class="col-md-12 mt-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ route('admin.syllabi.index') }}" class="btn btn-secondary">Clear</a>
                                <a href="{{ route('admin.syllabi.progress-report') }}" class="btn btn-info">View Progress Report</a>
                            </div>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Chapters</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($syllabi as $syllabus)
                                <tr>
                                    <td>{{ Str::limit($syllabus->title, 30) }}</td>
                                    <td>{{ $syllabus->subject }}</td>
                                    <td>{{ $syllabus->class_name }}</td>
                                    <td>{{ $syllabus->section }}</td>
                                    <td>{{ $syllabus->start_date ? $syllabus->start_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>{{ $syllabus->end_date ? $syllabus->end_date->format('d-m-Y') : 'N/A' }}</td>
                                    <td>
                                        @if($syllabus->hasChapters())
                                            <span class="badge bg-primary">{{ $syllabus->getChapterCount() }} chapters</span>
                                        @else
                                            <span class="badge bg-secondary">No chapters</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $syllabus->status == 'active' ? 'success' : ($syllabus->status == 'archived' ? 'secondary' : 'warning') }}">
                                            {{ ucfirst($syllabus->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $syllabus->createdBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.syllabi.show', $syllabus) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.syllabi.edit', $syllabus) }}" class="btn btn-sm btn-primary">Edit</a>
                                            
                                            <form action="{{ route('admin.syllabi.destroy', $syllabus) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this syllabus?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No syllabi found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        {{ $syllabi->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection